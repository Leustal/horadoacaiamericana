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

$nome = isset($dados['nome'])
    ? trim((string) $dados['nome'])
    : '';

$telefone = isset($dados['telefone'])
    ? trim((string) $dados['telefone'])
    : '';

$email = isset($dados['email'])
    ? trim((string) $dados['email'])
    : '';

$endereco = isset($dados['endereco'])
    ? trim((string) $dados['endereco'])
    : '';

$numero = isset($dados['numero'])
    ? trim((string) $dados['numero'])
    : '';

$complemento = isset($dados['complemento'])
    ? trim((string) $dados['complemento'])
    : '';

$referencia = isset($dados['referencia'])
    ? trim((string) $dados['referencia'])
    : '';


/*
=====================================================
 LIMPAR TELEFONE
=====================================================
*/

$telefoneNumeros = preg_replace(
    '/\D+/',
    '',
    $telefone
);


/*
=====================================================
 VALIDAÇÕES
=====================================================
*/

if ($nome === '') {

    responder([
        'sucesso' => false,
        'mensagem' => 'Informe seu nome.'
    ], 422);
}


if (
    mb_strlen($nome) < 2 ||
    mb_strlen($nome) > 100
) {

    responder([
        'sucesso' => false,
        'mensagem' => 'Nome inválido.'
    ], 422);
}


if (
    $telefoneNumeros === null ||
    strlen($telefoneNumeros) < 10 ||
    strlen($telefoneNumeros) > 11
) {

    responder([
        'sucesso' => false,
        'mensagem' => 'Telefone inválido.'
    ], 422);
}


if (
    $email === '' ||
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    responder([
        'sucesso' => false,
        'mensagem' => 'E-mail inválido.'
    ], 422);
}


if (mb_strlen($email) > 150) {

    responder([
        'sucesso' => false,
        'mensagem' => 'E-mail muito longo.'
    ], 422);
}


if ($endereco === '') {

    responder([
        'sucesso' => false,
        'mensagem' => 'Informe seu endereço.'
    ], 422);
}


if ($numero === '') {

    responder([
        'sucesso' => false,
        'mensagem' => 'Informe o número do endereço.'
    ], 422);
}


/*
=====================================================
 MONTAR ENDEREÇO COMPLETO
=====================================================
*/

$enderecoCompleto =
    $endereco .
    ', nº ' .
    $numero;


if ($complemento !== '') {

    $enderecoCompleto .=
        ', ' .
        $complemento;
}


if ($referencia !== '') {

    $enderecoCompleto .=
        ' | Referência: ' .
        $referencia;
}


/*
=====================================================
 PROCESSAR CLIENTE
=====================================================
*/

try {

    /*
    =================================================
     PROCURAR CLIENTE PELO TELEFONE
    =================================================
    */

    $stmtBusca = $pdo->prepare("
        SELECT
            id,
            nome,
            telefone,
            email,
            endereco
        FROM clientes
        WHERE telefone = :telefone
        LIMIT 1
    ");

    $stmtBusca->execute([
        ':telefone' => $telefoneNumeros
    ]);

    $cliente =
        $stmtBusca->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    =================================================
     CLIENTE JÁ EXISTE
    =================================================
    */

    if ($cliente) {

        $stmtUpdate = $pdo->prepare("
            UPDATE clientes

            SET
                nome = :nome,
                email = :email,
                endereco = :endereco

            WHERE id = :id
        ");

        $stmtUpdate->execute([

            ':nome' =>
                $nome,

            ':email' =>
                $email,

            ':endereco' =>
                $enderecoCompleto,

            ':id' =>
                $cliente['id']
        ]);


        responder([

            'sucesso' => true,

            'cliente_id' =>
                (int) $cliente['id'],

            'novo_cliente' =>
                false,

            'mensagem' =>
                'Dados do cliente atualizados com sucesso.'

        ]);
    }


    /*
    =================================================
     CRIAR NOVO CLIENTE
    =================================================

     IMPORTANTE:
     A coluna senha da tabela clientes precisa
     permitir NULL para este fluxo de checkout.

     Caso você tenha mantido senha como NOT NULL,
     precisamos ajustar isso no banco.
    =================================================
    */

    $stmtInsert = $pdo->prepare("
        INSERT INTO clientes (
            nome,
            telefone,
            email,
            senha,
            endereco
        )
        VALUES (
            :nome,
            :telefone,
            :email,
            NULL,
            :endereco
        )
    ");


    $stmtInsert->execute([

        ':nome' =>
            $nome,

        ':telefone' =>
            $telefoneNumeros,

        ':email' =>
            $email,

        ':endereco' =>
            $enderecoCompleto
    ]);


    $clienteId =
        (int) $pdo->lastInsertId();


    responder([

        'sucesso' => true,

        'cliente_id' =>
            $clienteId,

        'novo_cliente' =>
            true,

        'mensagem' =>
            'Cliente cadastrado com sucesso.'

    ], 201);


} catch (PDOException $e) {

    /*
    =================================================
     LOG INTERNO
    =================================================
    */

    error_log(
        '[HORA DO ACAI] Erro em clientes.php: ' .
        $e->getMessage()
    );


    /*
    =================================================
     TRATAMENTO DE TELEFONE DUPLICADO
    =================================================
    */

    if (
        isset($e->errorInfo[1]) &&
        (int) $e->errorInfo[1] === 1062
    ) {

        responder([
            'sucesso' => false,
            'mensagem' =>
                'Este telefone já está cadastrado.'
        ], 409);
    }


    /*
    =================================================
     ERRO GENÉRICO
    =================================================
    */

    responder([
        'sucesso' => false,
        'mensagem' =>
            'Não foi possível salvar os dados do cliente.'
    ], 500);


} catch (Throwable $e) {

    error_log(
        '[HORA DO ACAI] Erro em clientes.php: ' .
        $e->getMessage()
    );


    responder([
        'sucesso' => false,
        'mensagem' =>
            'Não foi possível processar os dados do cliente.'
    ], 500);
}