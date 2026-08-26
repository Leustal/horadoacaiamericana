<?php
// /api/cardapio.php

// 1. Cabeçalhos (Headers) para permitir acesso e definir o retorno como JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Permite que o frontend acesse essa API
header('Access-Control-Allow-Methods: GET');

// 2. Inclui a nossa conexão com o banco de dados
// Como o arquivo está na pasta api/, voltamos um nível (../) para achar o conexao.php
// No arquivo api/cardapio.php
require_once __DIR__ . '/../../conexao.php';

try {
    // 3. A Query Mágica (JOIN)
    // Vamos buscar Categorias, Produtos e Tamanhos em uma única consulta ao banco
    $sql = "
        SELECT 
            c.id AS categoria_id, c.nome AS categoria_nome,
            p.id AS produto_id, p.nome AS produto_nome, p.descricao AS produto_descricao,
            pt.id AS tamanho_id, pt.tamanho, pt.preco
        FROM categorias c
        LEFT JOIN produtos p ON c.id = p.categoria_id AND p.ativo = 1
        LEFT JOIN produtos_tamanhos pt ON p.id = pt.produto_id
        ORDER BY c.id, p.id, pt.preco ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll();

    // 4. Organizando os dados (Agrupando Produtos dentro das Categorias)
    $cardapio = [];

    foreach ($resultados as $row) {
        $cat_id = $row['categoria_id'];
        $prod_id = $row['produto_id'];

        // Cria a categoria se ela ainda não existir no nosso array
        if (!isset($cardapio[$cat_id])) {
            $cardapio[$cat_id] = [
                'id' => $cat_id,
                'categoria' => $row['categoria_nome'],
                'produtos' => []
            ];
        }

        // Se houver um produto vinculado a essa categoria
        if ($prod_id) {
            // Cria o produto se ele ainda não existir na categoria
            if (!isset($cardapio[$cat_id]['produtos'][$prod_id])) {
                $cardapio[$cat_id]['produtos'][$prod_id] = [
                    'id' => $prod_id,
                    'nome' => $row['produto_nome'],
                    'descricao' => $row['produto_descricao'],
                    'tamanhos' => []
                ];
            }

            // Adiciona o tamanho e o preço ao produto
            if ($row['tamanho_id']) {
                $cardapio[$cat_id]['produtos'][$prod_id]['tamanhos'][] = [
                    'id' => $row['tamanho_id'],
                    'tamanho' => $row['tamanho'],
                    'preco' => (float) $row['preco'] // Garante que o preço seja um número decimal
                ];
            }
        }
    }

    // 5. Limpando os índices do Array para gerar um JSON limpo (como uma lista)
    $jsonFinal = array_values($cardapio);
    foreach ($jsonFinal as &$categoria) {
        $categoria['produtos'] = array_values($categoria['produtos']);
    }

    // 6. Retorna o JSON para a tela
    echo json_encode($jsonFinal, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Se der erro, retorna um JSON com a mensagem (útil para debug)
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar o cardápio: ' . $e->getMessage()]);
}
?>