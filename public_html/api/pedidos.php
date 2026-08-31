<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../conexao.php';


/* =====================================================
   PERMITIR APENAS POST
===================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ]);

    exit;
}


/* =====================================================
   RECEBER JSON
===================================================== */

$conteudo = file_get_contents('php://input');

$dados = json_decode($conteudo, true);


/* =====================================================
   VALIDAR JSON
===================================================== */

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($dados)
) {

    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Dados inválidos.'
    ]);

    exit;
}


/* =====================================================
   VALIDAR CAMPOS PRINCIPAIS
===================================================== */

$clienteId = $dados['cliente_id'] ?? null;

$metodoPagamento = $dados['metodo_pagamento'] ?? '';

$total = isset($dados['total'])
    ? (float) $dados['total']
    : 0;

$trocoPara = isset($dados['troco_para'])
    ? (float) $dados['troco_para']
    : null;

$itens = $dados['itens'] ?? [];


if (
    !$clienteId ||
    !$metodoPagamento ||
    $total <= 0 ||
    !is_array($itens) ||
    count($itens) === 0
) {

    http_response_code(422);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Dados obrigatórios não preenchidos.'
    ]);

    exit;
}


/* =====================================================
   VALIDAR MÉTODO DE PAGAMENTO
===================================================== */

$metodosPermitidos = [
    'pix',
    'cartao',
    'dinheiro'
];

if (!in_array($metodoPagamento, $metodosPermitidos)) {

    http_response_code(422);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método de pagamento inválido.'
    ]);

    exit;
}


/* =====================================================
   DEFINIR STATUS PAGAMENTO
===================================================== */

$statusPagamento = 'aguardando';

if ($metodoPagamento === 'dinheiro') {

    $statusPagamento = 'aguardando';

}


/* =====================================================
   SALVAR PEDIDO
===================================================== */

try {

    $pdo->beginTransaction();


    /* ===============================
       CRIAR PEDIDO
    =============================== */

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
            :status_pagamento,
            :troco_para

        )

    ");


    $stmtPedido->execute([

        ':cliente_id' => $clienteId,

        ':total' => $total,

        ':metodo_pagamento' => $metodoPagamento,

        ':status_pagamento' => $statusPagamento,

        ':troco_para' => $trocoPara

    ]);


    $pedidoId = $pdo->lastInsertId();


    /* ===============================
       INSERIR ITENS
    =============================== */

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


    $stmtAdicional = $pdo->prepare("

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


    foreach ($itens as $item) {


        $produtoTamanhoId =
            $item['produto_tamanho_id'] ?? null;

        $quantidade =
            isset($item['quantidade'])
                ? (int) $item['quantidade']
                : 1;

        $precoUnitario =
            isset($item['preco_unitario'])
                ? (float) $item['preco_unitario']
                : 0;

        $observacoes =
            $item['observacoes'] ?? null;


        if (
            !$produtoTamanhoId ||
            $quantidade <= 0 ||
            $precoUnitario < 0
        ) {

            throw new Exception(
                'Item do pedido inválido.'
            );

        }


        $stmtItem->execute([

            ':pedido_id' => $pedidoId,

            ':produto_tamanho_id' =>
                $produtoTamanhoId,

            ':quantidade' =>
                $quantidade,

            ':preco_unitario' =>
                $precoUnitario,

            ':observacoes' =>
                $observacoes

        ]);


        $itemPedidoId =
            $pdo->lastInsertId();


        /* ===============================
           ADICIONAIS
        =============================== */

        if (
            isset($item['adicionais']) &&
            is_array($item['adicionais'])
        ) {

            foreach (
                $item['adicionais']
                as $adicional
            ) {


                $adicionalId =
                    $adicional['adicional_id']
                    ?? null;

                $precoAdicional =
                    isset(
                        $adicional['preco_adicional']
                    )
                        ? (float)
                            $adicional[
                                'preco_adicional'
                            ]
                        : 0;


                if (!$adicionalId) {

                    continue;

                }


                $stmtAdicional->execute([

                    ':item_pedido_id' =>
                        $itemPedidoId,

                    ':adicional_id' =>
                        $adicionalId,

                    ':preco_adicional' =>
                        $precoAdicional

                ]);

            }

        }

    }


    /* ===============================
       CONFIRMAR TRANSAÇÃO
    =============================== */

    $pdo->commit();


    echo json_encode([

        'sucesso' => true,

        'pedido_id' =>
            (int) $pedidoId,

        'mensagem' =>
            'Pedido criado com sucesso.'

    ]);


} catch (Exception $e) {


    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    http_response_code(500);


    echo json_encode([

        'sucesso' => false,

        'mensagem' =>
            'Erro ao criar pedido.',

        'detalhes' =>
            $e->getMessage()

    ]);

}