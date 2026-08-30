<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../conexao.php';


/* =====================================================
   FUNÇÃO PARA RETORNAR JSON
===================================================== */

function responder($sucesso, $dados = [], $codigo = 200)
{
    http_response_code($codigo);

    echo json_encode(
        array_merge(
            ['sucesso' => $sucesso],
            $dados
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/* =====================================================
   ACEITA APENAS POST
===================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(
        false,
        ['mensagem' => 'Método não permitido.'],
        405
    );
}


/* =====================================================
   RECEBER JSON
===================================================== */

$dados = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($dados)) {
    responder(
        false,
        ['mensagem' => 'Dados inválidos.'],
        400
    );
}


/* =====================================================
   DADOS DO CLIENTE
===================================================== */

$nome = trim($dados['cliente_nome'] ?? '');

$telefone = preg_replace(
    '/\D/',
    '',
    $dados['cliente_telefone'] ?? ''
);

$endereco = trim(
    $dados['endereco'] ?? ''
);

$bairroId = intval(
    $dados['bairro_id'] ?? 0
);

$metodoPagamento = trim(
    $dados['metodo_pagamento'] ?? ''
);

$trocoPara = isset($dados['troco_para'])
    ? floatval($dados['troco_para'])
    : 0;


/* =====================================================
   ITENS
===================================================== */

$itens = $dados['itens'] ?? [];


/* =====================================================
   VALIDAÇÕES
===================================================== */

if (empty($nome)) {
    responder(
        false,
        ['mensagem' => 'Informe seu nome.'],
        422
    );
}

if (strlen($telefone) < 10) {
    responder(
        false,
        ['mensagem' => 'Informe um telefone válido.'],
        422
    );
}

if (empty($endereco)) {
    responder(
        false,
        ['mensagem' => 'Informe o endereço de entrega.'],
        422
    );
}

if ($bairroId <= 0) {
    responder(
        false,
        ['mensagem' => 'Selecione seu bairro.'],
        422
    );
}

if (
    !in_array(
        $metodoPagamento,
        ['pix', 'cartao', 'dinheiro']
    )
) {
    responder(
        false,
        ['mensagem' => 'Método de pagamento inválido.'],
        422
    );
}

if (!is_array($itens) || count($itens) === 0) {
    responder(
        false,
        ['mensagem' => 'Seu carrinho está vazio.'],
        422
    );
}


/* =====================================================
   INICIAR TRANSAÇÃO
===================================================== */

try {

    $pdo->beginTransaction();


    /* =====================================================
       BUSCAR BAIRRO E TAXA
    ===================================================== */

    $stmtBairro = $pdo->prepare(
        "SELECT id, nome, taxa
         FROM bairros
         WHERE id = ?"
    );

    $stmtBairro->execute([
        $bairroId
    ]);

    $bairro = $stmtBairro->fetch();

    if (!$bairro) {
        throw new Exception(
            'Bairro inválido.'
        );
    }

    $taxaEntrega = floatval(
        $bairro['taxa']
    );


    /* =====================================================
       CLIENTE
    ===================================================== */

    $stmtCliente = $pdo->prepare(
        "SELECT id
         FROM clientes
         WHERE telefone = ?
         LIMIT 1"
    );

    $stmtCliente->execute([
        $telefone
    ]);

    $cliente = $stmtCliente->fetch();


    if ($cliente) {

        $clienteId = intval(
            $cliente['id']
        );

        $stmtAtualizarCliente = $pdo->prepare(
            "UPDATE clientes
             SET nome = ?,
                 endereco = ?
             WHERE id = ?"
        );

        $stmtAtualizarCliente->execute([
            $nome,
            $endereco,
            $clienteId
        ]);

    } else {

        $stmtNovoCliente = $pdo->prepare(
            "INSERT INTO clientes
             (nome, telefone, endereco)
             VALUES (?, ?, ?)"
        );

        $stmtNovoCliente->execute([
            $nome,
            $telefone,
            $endereco
        ]);

        $clienteId = intval(
            $pdo->lastInsertId()
        );
    }


    /* =====================================================
       RECALCULAR PEDIDO
    ===================================================== */

    $subtotal = 0;

    $itensProcessados = [];


    foreach ($itens as $item) {

        $produtoTamanhoId = intval(
            $item['tamanhoId'] ?? 0
        );

        $quantidade = intval(
            $item['quantidade'] ?? 1
        );

        $observacao = trim(
            $item['observacao'] ?? ''
        );

        if (
            $produtoTamanhoId <= 0 ||
            $quantidade <= 0
        ) {
            throw new Exception(
                'Item do pedido inválido.'
            );
        }


        /* =====================================================
           BUSCAR TAMANHO E PREÇO REAL
        ===================================================== */

        $stmtProduto = $pdo->prepare(
            "SELECT
                pt.id,
                pt.preco,
                pt.produto_id,
                p.nome,
                p.ativo

             FROM produtos_tamanhos pt

             INNER JOIN produtos p
                ON p.id = pt.produto_id

             WHERE pt.id = ?
             LIMIT 1"
        );

        $stmtProduto->execute([
            $produtoTamanhoId
        ]);

        $produtoBanco = $stmtProduto->fetch();

        if (
            !$produtoBanco ||
            intval($produtoBanco['ativo']) !== 1
        ) {
            throw new Exception(
                'Um produto do pedido não está mais disponível.'
            );
        }


        $precoProduto = floatval(
            $produtoBanco['preco']
        );

        $totalAdicionais = 0;

        $adicionaisProcessados = [];


        /* =====================================================
           PROCESSAR ADICIONAIS
        ===================================================== */

        $adicionais = $item['adicionais'] ?? [];


        if (!is_array($adicionais)) {
            $adicionais = [];
        }


        foreach ($adicionais as $adicional) {

            $adicionalId = intval(
                $adicional['id'] ?? 0
            );

            /*
             * No app.js antigo você envia
             * "2x Nutella" no nome,
             * então vamos receber a quantidade
             * diretamente quando atualizarmos o checkout.
             */

            $quantidadeAdicional = intval(
                $adicional['quantidade'] ?? 1
            );


            if (
                $adicionalId <= 0 ||
                $quantidadeAdicional <= 0
            ) {
                continue;
            }


            $stmtAdicional = $pdo->prepare(
                "SELECT id, nome, preco
                 FROM adicionais
                 WHERE id = ?
                 AND ativo = 1
                 LIMIT 1"
            );

            $stmtAdicional->execute([
                $adicionalId
            ]);

            $adicionalBanco = $stmtAdicional->fetch();


            if (!$adicionalBanco) {
                throw new Exception(
                    'Um adicional selecionado não está mais disponível.'
                );
            }


            $precoAdicionalUnitario = floatval(
                $adicionalBanco['preco']
            );


            $precoAdicionalTotal =
                $precoAdicionalUnitario *
                $quantidadeAdicional;


            $totalAdicionais +=
                $precoAdicionalTotal;


            $adicionaisProcessados[] = [

                'adicional_id' =>
                    intval($adicionalBanco['id']),

                'quantidade' =>
                    $quantidadeAdicional,

                'preco_total' =>
                    $precoAdicionalTotal

            ];
        }


        /* =====================================================
           TOTAL DO ITEM
        ===================================================== */

        $totalItem =
            (
                $precoProduto +
                $totalAdicionais
            )
            *
            $quantidade;


        $subtotal +=
            $totalItem;


        $itensProcessados[] = [

            'produto_tamanho_id' =>
                $produtoTamanhoId,

            'quantidade' =>
                $quantidade,

            'preco_unitario' =>
                $precoProduto,

            'observacoes' =>
                $observacao,

            'adicionais' =>
                $adicionaisProcessados

        ];
    }


    /* =====================================================
       TOTAL FINAL
    ===================================================== */

    $totalFinal =
        $subtotal +
        $taxaEntrega;


    /* =====================================================
       CRIAR PEDIDO
    ===================================================== */

    $statusPagamento =
        $metodoPagamento === 'pix'
            ? 'aguardando'
            : 'aguardando';


    $stmtPedido = $pdo->prepare(
        "INSERT INTO pedidos
        (
            cliente_id,
            bairro_id,
            endereco_entrega,
            total,
            taxa_entrega,
            status,
            metodo_pagamento,
            status_pagamento,
            troco_para
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            'pendente',
            ?,
            ?,
            ?
        )"
    );


    $stmtPedido->execute([

        $clienteId,

        $bairroId,

        $endereco,

        $totalFinal,

        $taxaEntrega,

        $metodoPagamento,

        $statusPagamento,

        $metodoPagamento === 'dinheiro'
            ? $trocoPara
            : null

    ]);


    $pedidoId = intval(
        $pdo->lastInsertId()
    );


    /* =====================================================
       INSERIR ITENS
    ===================================================== */

    $stmtItemPedido = $pdo->prepare(
        "INSERT INTO itens_pedido
        (
            pedido_id,
            produto_tamanho_id,
            quantidade,
            preco_unitario,
            observacoes
        )

        VALUES (?, ?, ?, ?, ?)"
    );


    $stmtAdicionalPedido = $pdo->prepare(
        "INSERT INTO itens_pedido_adicionais
        (
            item_pedido_id,
            adicional_id,
            quantidade,
            preco_adicional
        )

        VALUES (?, ?, ?, ?)"
    );


    foreach ($itensProcessados as $item) {

        $stmtItemPedido->execute([

            $pedidoId,

            $item['produto_tamanho_id'],

            $item['quantidade'],

            $item['preco_unitario'],

            $item['observacoes']

        ]);


        $itemPedidoId = intval(
            $pdo->lastInsertId()
        );


        foreach (
            $item['adicionais']
            as $adicional
        ) {

            $stmtAdicionalPedido->execute([

                $itemPedidoId,

                $adicional['adicional_id'],

                $adicional['quantidade'],

                $adicional['preco_total']

            ]);
        }
    }


    /* =====================================================
       FINALIZAR TRANSAÇÃO
    ===================================================== */

    $pdo->commit();


    responder(
        true,
        [

            'mensagem' =>
                'Pedido criado com sucesso.',

            'pedido_id' =>
                $pedidoId,

            'subtotal' =>
                round(
                    $subtotal,
                    2
                ),

            'taxa_entrega' =>
                round(
                    $taxaEntrega,
                    2
                ),

            'total' =>
                round(
                    $totalFinal,
                    2
                ),

            'metodo_pagamento' =>
                $metodoPagamento

        ]
    );


} catch (Throwable $e) {

    if (
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }


    error_log(
        'Erro pedido: ' .
        $e->getMessage()
    );


    responder(
        false,
        [
            'mensagem' =>
                'Não foi possível finalizar o pedido.'
        ],
        500
    );
}