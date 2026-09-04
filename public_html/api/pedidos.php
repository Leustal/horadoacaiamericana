<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../conexao.php';


/*
=====================================================
 FUNÇÃO PADRÃO DE RESPOSTA
=====================================================
*/

function responder(array $dados, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
=====================================================
 PERMITIR APENAS POST
=====================================================
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responder([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ], 405);
}


/*
=====================================================
 LER JSON
=====================================================
*/

$conteudo = file_get_contents('php://input');

$dados = json_decode($conteudo, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($dados)
) {

    responder([
        'sucesso' => false,
        'mensagem' => 'Dados inválidos.'
    ], 400);
}


/*
=====================================================
 RECEBER DADOS
=====================================================
*/

$clienteId = isset($dados['cliente_id'])
    ? (int) $dados['cliente_id']
    : 0;

$metodoPagamento = isset($dados['metodo_pagamento'])
    ? strtolower(trim((string) $dados['metodo_pagamento']))
    : '';

$itens = $dados['itens'] ?? [];

$trocoPara = null;

if (
    isset($dados['troco_para']) &&
    $dados['troco_para'] !== '' &&
    $dados['troco_para'] !== null
) {

    $trocoPara = (float) $dados['troco_para'];
}


/*
=====================================================
 VALIDAR DADOS PRINCIPAIS
=====================================================
*/

if ($clienteId <= 0) {

    responder([
        'sucesso' => false,
        'mensagem' => 'Cliente inválido.'
    ], 422);
}


if (!in_array(
    $metodoPagamento,
    ['pix', 'cartao', 'dinheiro'],
    true
)) {

    responder([
        'sucesso' => false,
        'mensagem' => 'Método de pagamento inválido.'
    ], 422);
}


if (
    !is_array($itens) ||
    count($itens) === 0
) {

    responder([
        'sucesso' => false,
        'mensagem' => 'O pedido precisa possuir pelo menos um item.'
    ], 422);
}


/*
=====================================================
 VALIDAR TROCO
=====================================================
*/

if ($metodoPagamento === 'dinheiro') {

    if (
        $trocoPara !== null &&
        $trocoPara < 0
    ) {

        responder([
            'sucesso' => false,
            'mensagem' => 'Valor de troco inválido.'
        ], 422);
    }

} else {

    // Para PIX ou cartão não existe troco.
    $trocoPara = null;
}


/*
=====================================================
 INICIAR TRANSAÇÃO
=====================================================
*/

try {

    $pdo->beginTransaction();


    /*
    =====================================================
     VALIDAR CLIENTE
    =====================================================
    */

    $stmtCliente = $pdo->prepare("
        SELECT
            id,
            nome,
            telefone,
            email
        FROM clientes
        WHERE id = :id
        LIMIT 1
    ");

    $stmtCliente->execute([
        ':id' => $clienteId
    ]);

    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);


    if (!$cliente) {

        throw new Exception('Cliente não encontrado.');
    }


    /*
    =====================================================
     PREPARAR INSERT DO PEDIDO
    =====================================================
    */

    $stmtPedido = $pdo->prepare("
        INSERT INTO pedidos (
            cliente_id,
            total,
            status,
            metodo_pagamento,
            status_pagamento,
            troco_para
        )
        VALUES (
            :cliente_id,
            :total,
            'pendente',
            :metodo_pagamento,
            'aguardando',
            :troco_para
        )
    ");


    /*
    =====================================================
     IMPORTANTE

     O total recebido do navegador NÃO é utilizado.

     O total será calculado com base nos produtos
     e adicionais existentes no banco.
    =====================================================
    */

    $totalPedido = 0.00;


    /*
    =====================================================
     PREPARAR CONSULTAS
    =====================================================
    */

    $stmtTamanho = $pdo->prepare("
        SELECT
            id,
            preco
        FROM produtos_tamanhos
        WHERE id = :id
        LIMIT 1
    ");


    $stmtAdicional = $pdo->prepare("
        SELECT
            id,
            preco,
            ativo
        FROM adicionais
        WHERE id = :id
        LIMIT 1
    ");


    /*
    =====================================================
     PRIMEIRA PASSAGEM
     
     Validar todos os itens e calcular o total real.
    =====================================================
    */

    $itensProcessados = [];


    foreach ($itens as $indice => $item) {

        if (!is_array($item)) {

            throw new Exception(
                'Item inválido na posição ' . $indice . '.'
            );
        }


        $produtoTamanhoId = isset($item['produto_tamanho_id'])
            ? (int) $item['produto_tamanho_id']
            : 0;


        $quantidade = isset($item['quantidade'])
            ? (int) $item['quantidade']
            : 0;


        $observacoes = isset($item['observacoes'])
            ? trim((string) $item['observacoes'])
            : null;


        /*
        -----------------------------------------------------
         VALIDAR ITEM
        -----------------------------------------------------
        */

        if ($produtoTamanhoId <= 0) {

            throw new Exception(
                'Tamanho de produto inválido.'
            );
        }


        if ($quantidade <= 0) {

            throw new Exception(
                'Quantidade de produto inválida.'
            );
        }


        /*
        -----------------------------------------------------
         BUSCAR PREÇO REAL DO BANCO
        -----------------------------------------------------
        */

        $stmtTamanho->execute([
            ':id' => $produtoTamanhoId
        ]);

        $tamanho = $stmtTamanho->fetch(PDO::FETCH_ASSOC);


        if (!$tamanho) {

            throw new Exception(
                'Produto/tamanho não encontrado.'
            );
        }


        $precoUnitario = (float) $tamanho['preco'];


        /*
        -----------------------------------------------------
         CALCULAR ADICIONAIS
        -----------------------------------------------------
        */

        $adicionaisProcessados = [];

        $totalAdicionais = 0.00;


        if (
            isset($item['adicionais']) &&
            is_array($item['adicionais'])
        ) {

            foreach ($item['adicionais'] as $adicional) {

                if (!is_array($adicional)) {
                    continue;
                }


                $adicionalId = isset($adicional['adicional_id'])
                    ? (int) $adicional['adicional_id']
                    : 0;


                $quantidadeAdicional = isset(
                    $adicional['quantidade']
                )
                    ? (int) $adicional['quantidade']
                    : 1;


                /*
                Se o frontend não enviar quantidade,
                consideramos 1.
                */

                if ($quantidadeAdicional <= 0) {
                    $quantidadeAdicional = 1;
                }


                if ($adicionalId <= 0) {
                    continue;
                }


                /*
                -------------------------------------------------
                 BUSCAR ADICIONAL NO BANCO
                -------------------------------------------------
                */

                $stmtAdicional->execute([
                    ':id' => $adicionalId
                ]);

                $dadosAdicional =
                    $stmtAdicional->fetch(PDO::FETCH_ASSOC);


                if (!$dadosAdicional) {

                    throw new Exception(
                        'Adicional não encontrado.'
                    );
                }


                /*
                -------------------------------------------------
                 VERIFICAR SE ESTÁ ATIVO
                -------------------------------------------------
                */

                if ((int) $dadosAdicional['ativo'] !== 1) {

                    throw new Exception(
                        'Um dos adicionais selecionados não está disponível.'
                    );
                }


                $precoAdicionalUnitario =
                    (float) $dadosAdicional['preco'];


                $precoAdicionalTotal =
                    $precoAdicionalUnitario *
                    $quantidadeAdicional;


                $totalAdicionais +=
                    $precoAdicionalTotal;


                $adicionaisProcessados[] = [
                    'adicional_id' => $adicionalId,
                    'preco_adicional' => $precoAdicionalUnitario
                ];
            }
        }


        /*
        -----------------------------------------------------
         TOTAL DO ITEM
        -----------------------------------------------------
        */

        $totalItem =
            (
                $precoUnitario +
                $totalAdicionais
            ) * $quantidade;


        $totalPedido += $totalItem;


        /*
        -----------------------------------------------------
         GUARDAR ITEM PROCESSADO
        -----------------------------------------------------
        */

        $itensProcessados[] = [
            'produto_tamanho_id' => $produtoTamanhoId,
            'quantidade' => $quantidade,
            'preco_unitario' => $precoUnitario,
            'observacoes' => $observacoes,
            'adicionais' => $adicionaisProcessados
        ];
    }


    /*
    =====================================================
     ARREDONDAMENTO DO TOTAL
    =====================================================
    */

    $totalPedido = round($totalPedido, 2);


    if ($totalPedido <= 0) {

        throw new Exception(
            'O valor total do pedido é inválido.'
        );
    }


    /*
    =====================================================
     VALIDAR TROCO CONTRA O TOTAL
    =====================================================
    */

    if ($metodoPagamento === 'dinheiro') {

        if (
            $trocoPara !== null &&
            $trocoPara > 0 &&
            $trocoPara < $totalPedido
        ) {

            throw new Exception(
                'O valor informado para troco é menor que o total do pedido.'
            );
        }
    }


    /*
    =====================================================
     CRIAR PEDIDO
    =====================================================
    */

    $stmtPedido->execute([
        ':cliente_id' => $clienteId,
        ':total' => $totalPedido,
        ':metodo_pagamento' => $metodoPagamento,
        ':troco_para' => $trocoPara
    ]);


    $pedidoId = (int) $pdo->lastInsertId();


    /*
    =====================================================
     PREPARAR INSERT DOS ITENS
    =====================================================
    */

    $stmtItem = $pdo->prepare("
        INSERT INTO itens_pedido (
            pedido_id,
            produto_tamanho_id,
            quantidade,
            preco_unitario,
            observacoes
        )
        VALUES (
            :pedido_id,
            :produto_tamanho_id,
            :quantidade,
            :preco_unitario,
            :observacoes
        )
    ");


    $stmtItemAdicional = $pdo->prepare("
        INSERT INTO itens_pedido_adicionais (
            item_pedido_id,
            adicional_id,
            preco_adicional
        )
        VALUES (
            :item_pedido_id,
            :adicional_id,
            :preco_adicional
        )
    ");


    /*
    =====================================================
     INSERIR ITENS
    =====================================================
    */

    foreach ($itensProcessados as $item) {

        $stmtItem->execute([
            ':pedido_id' => $pedidoId,
            ':produto_tamanho_id' =>
                $item['produto_tamanho_id'],
            ':quantidade' =>
                $item['quantidade'],
            ':preco_unitario' =>
                $item['preco_unitario'],
            ':observacoes' =>
                $item['observacoes']
        ]);


        $itemPedidoId =
            (int) $pdo->lastInsertId();


        /*
        -------------------------------------------------
         INSERIR ADICIONAIS
        -------------------------------------------------
        */

        foreach (
            $item['adicionais']
            as $adicional
        ) {

            $stmtItemAdicional->execute([
                ':item_pedido_id' =>
                    $itemPedidoId,

                ':adicional_id' =>
                    $adicional['adicional_id'],

                ':preco_adicional' =>
                    $adicional['preco_adicional']
            ]);
        }
    }


    /*
    =====================================================
     FINALIZAR TRANSAÇÃO
    =====================================================
    */

    $pdo->commit();


    /*
    =====================================================
     RESPOSTA
    =====================================================
    */

    responder([
        'sucesso' => true,
        'pedido_id' => $pedidoId,
        'total' => number_format(
            $totalPedido,
            2,
            '.',
            ''
        ),
        'metodo_pagamento' => $metodoPagamento,
        'status_pagamento' => 'aguardando',
        'mensagem' => 'Pedido criado com sucesso.'
    ], 201);


} catch (Throwable $e) {

    /*
    =====================================================
     ROLLBACK
    =====================================================
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    /*
    =====================================================
     LOG INTERNO

     O erro real não é enviado ao navegador.
    =====================================================
    */

    error_log(
        '[HORA DO ACAI] Erro em pedidos.php: ' .
        $e->getMessage()
    );


    /*
    =====================================================
     RESPOSTA SEGURA
    =====================================================
    */

    responder([
        'sucesso' => false,
        'mensagem' => 'Não foi possível criar o pedido. Tente novamente.'
    ], 500);
}

