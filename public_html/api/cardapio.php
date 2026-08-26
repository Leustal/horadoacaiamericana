<?php
// public_html/api/cardapio.php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

try {
    // Busca categorias
    $stmtCat = $pdo->query("SELECT id, nome FROM categorias ORDER BY id");
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    $resultado = [];

    foreach ($categorias as $cat) {
        // Busca produtos da categoria
        $stmtProd = $pdo->prepare("SELECT id, nome, descricao FROM produtos WHERE categoria_id = ?");
        $stmtProd->execute([$cat['id']]);
        $produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

        $produtosComTamanhos = [];

        foreach ($produtos as $prod) {
            // Busca tamanhos do produto
            $stmtTam = $pdo->prepare("SELECT id, nome as tamanho, preco FROM produtos_tamanhos WHERE produto_id = ?");
            $stmtTam->execute([$prod['id']]);
            $tamanhosBanco = $stmtTam->fetchAll(PDO::FETCH_ASSOC);

            // FORÇA O PREÇO PARA 0.01 PARA TESTES
            $tamanhosModificados = [];
            foreach ($tamanhosBanco as $t) {
                $tamanhosModificados[] = [
                    'id' => $t['id'],
                    'tamanho' => $t['tamanho'],
                    'preco' => 0.01 
                ];
            }

            $produtosComTamanhos[] = [
                'id' => $prod['id'],
                'nome' => $prod['nome'],
                'descricao' => $prod['descricao'],
                'tamanhos' => $tamanhosModificados
            ];
        }

        $resultado[] = [
            'id' => $cat['id'],
            'categoria' => $cat['nome'],
            'produtos' => $produtosComTamanhos
        ];
    }

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>