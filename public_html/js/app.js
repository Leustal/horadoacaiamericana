/* =========================================================
   HORA DO AÇAÍ
   APP.JS
========================================================= */

'use strict';


/* =========================================================
   CONFIGURAÇÕES
========================================================= */

const API = '/api';

const CHAVE_CARRINHO = 'carrinhoAcai';


/* =========================================================
   ESTADO GLOBAL
========================================================= */

let carrinho = [];

let cardapioGlobal = [];

let adicionaisGlobais = [];

let produtoSelecionado = null;

let tamanhoSelecionado = null;

let lojaAbertaGlobal = false;


/* =========================================================
   INICIALIZAÇÃO
========================================================= */

document.addEventListener('DOMContentLoaded', () => {

    carregarCarrinho();

    atualizarContadorCarrinho();

    verificarPagina();

});


/* =========================================================
   IDENTIFICAR PÁGINA
========================================================= */

function verificarPagina() {

    const pagina =
        window.location.pathname
            .split('/')
            .pop()
            .toLowerCase();


    if (
        pagina === 'checkout.html' ||
        pagina === 'checkout'
    ) {

        inicializarCheckout();

        return;
    }


    inicializarCardapio();
}


/* =========================================================
   CARRINHO
========================================================= */

function carregarCarrinho() {

    try {

        const salvo =
            localStorage.getItem(
                CHAVE_CARRINHO
            );


        carrinho =
            salvo
                ? JSON.parse(salvo)
                : [];


        if (!Array.isArray(carrinho)) {
            carrinho = [];
        }

    } catch (erro) {

        console.error(
            'Erro ao carregar carrinho:',
            erro
        );

        carrinho = [];
    }
}


/* =========================================================
   SALVAR CARRINHO
========================================================= */

function salvarCarrinho() {

    localStorage.setItem(
        CHAVE_CARRINHO,
        JSON.stringify(carrinho)
    );

    atualizarContadorCarrinho();
}


/* =========================================================
   CONTADOR
========================================================= */

function atualizarContadorCarrinho() {

    const total =
        carrinho.reduce(
            (soma, item) =>
                soma + (
                    Number(item.quantidade) || 0
                ),
            0
        );


    const contador =
        document.getElementById(
            'contadorCarrinho'
        );


    if (contador) {

        contador.textContent = total;

        contador.style.display =
            total > 0
                ? 'flex'
                : 'none';
    }
}


/* =========================================================
   TOTAL DO ITEM
========================================================= */

function calcularTotalItem(item) {

    const quantidade =
        Number(item.quantidade) || 1;


    const preco =
        Number(item.precoTotal) || 0;


    return preco * quantidade;
}


/* =========================================================
   TOTAL DO CARRINHO
========================================================= */

function calcularSubtotalCarrinho() {

    return carrinho.reduce(
        (total, item) =>
            total + calcularTotalItem(item),
        0
    );
}


/* =========================================================
   FORMATAR MOEDA
========================================================= */

function formatarMoeda(valor) {

    return Number(valor || 0)
        .toLocaleString(
            'pt-BR',
            {
                style: 'currency',
                currency: 'BRL'
            }
        );
}


/* =========================================================
   INICIALIZAR CARDÁPIO
========================================================= */

function inicializarCardapio() {

    carregarCardapio();

    carregarAdicionais();

    verificarHorarioLoja();

}


/* =========================================================
   CARREGAR CARDÁPIO
========================================================= */

async function carregarCardapio() {

    try {

        const resposta =
            await fetch(
                `${API}/cardapio.php`,
                {
                    method: 'GET',
                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            );


        const dados =
            await resposta.json();


        if (!dados || dados.sucesso === false) {

            throw new Error(
                dados?.mensagem ||
                'Erro ao carregar cardápio.'
            );
        }


        cardapioGlobal =
            dados.dados ||
            dados.cardapio ||
            dados ||
            [];


        renderizarCardapio();


    } catch (erro) {

        console.error(
            'Erro ao carregar cardápio:',
            erro
        );


        mostrarToast(
            'Não foi possível carregar o cardápio.',
            'erro'
        );
    }
}


/* =========================================================
   CARREGAR ADICIONAIS
========================================================= */

async function carregarAdicionais() {

    try {

        const resposta =
            await fetch(
                `${API}/adicionais.php`,
                {
                    method: 'GET',
                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            );


        const dados =
            await resposta.json();


        if (!dados || dados.sucesso === false) {

            throw new Error(
                dados?.mensagem ||
                'Erro ao carregar adicionais.'
            );
        }


        adicionaisGlobais =
            dados.dados ||
            dados.adicionais ||
            dados ||
            [];


    } catch (erro) {

        console.error(
            'Erro ao carregar adicionais:',
            erro
        );
    }
}


/* =========================================================
   RENDERIZAR CARDÁPIO
========================================================= */

function renderizarCardapio() {

    /*
    Mantemos compatibilidade com os IDs
    existentes no index.html.
    */

    const container =
        document.getElementById(
            'cardapio'
        );


    if (!container) {
        return;
    }


    if (!Array.isArray(cardapioGlobal)) {
        return;
    }


    /*
    Caso a API já retorne o HTML/estrutura
    antiga, não tentamos reconstruir.
    */

    // A renderização específica do seu index.html
    // pode continuar utilizando as funções existentes.
}


/* =========================================================
   HORÁRIO DA LOJA
========================================================= */

function verificarHorarioLoja() {

    const agora = new Date();

    const minutosAgora =
        agora.getHours() * 60 +
        agora.getMinutes();


    const abertura =
        14 * 60;


    const fechamento =
        23 * 60;


    lojaAbertaGlobal =
        minutosAgora >= abertura &&
        minutosAgora < fechamento;


    const status =
        document.getElementById(
            'statusLoja'
        );


    if (status) {

        status.textContent =
            lojaAbertaGlobal
                ? 'Aberta agora'
                : 'Fechada agora';
    }
}


/* =========================================================
   ABRIR CARRINHO
========================================================= */

function abrirCarrinho() {

    const drawer =
        document.getElementById(
            'cartDrawer'
        );


    if (!drawer) {
        return;
    }


    drawer.classList.add('active');

    renderizarCarrinho();
}


/* =========================================================
   FECHAR CARRINHO
========================================================= */

function fecharCarrinho() {

    const drawer =
        document.getElementById(
            'cartDrawer'
        );


    if (!drawer) {
        return;
    }


    drawer.classList.remove('active');
}


/* =========================================================
   RENDERIZAR CARRINHO
========================================================= */

function renderizarCarrinho() {

    const container =
        document.getElementById(
            'cartItems'
        );


    if (!container) {
        return;
    }


    container.innerHTML = '';


    if (carrinho.length === 0) {

        container.innerHTML = `
            <div class="carrinho-vazio">
                <div>🛒</div>
                <h3>Seu carrinho está vazio</h3>
                <p>Adicione um açaí para começar.</p>
            </div>
        `;

        return;
    }


    carrinho.forEach(
        (item, indice) => {

            const div =
                document.createElement(
                    'div'
                );


            div.className =
                'cart-item';


            div.innerHTML = `
                <div class="cart-item-info">

                    <strong>
                        ${escaparHTML(item.nome || 'Açaí')}
                    </strong>

                    <span>
                        ${escaparHTML(
                            item.tamanhoNome || ''
                        )}
                    </span>

                    <small>
                        ${formatarAdicionais(item)}
                    </small>

                </div>

                <div class="cart-item-actions">

                    <button
                        type="button"
                        onclick="alterarQuantidadeCarrinho(${indice}, -1)"
                    >
                        −
                    </button>

                    <strong>
                        ${item.quantidade || 1}
                    </strong>

                    <button
                        type="button"
                        onclick="alterarQuantidadeCarrinho(${indice}, 1)"
                    >
                        +
                    </button>

                </div>

                <div class="cart-item-price">

                    ${formatarMoeda(
                        calcularTotalItem(item)
                    )}

                </div>
            `;


            container.appendChild(div);
        }
    );
}


/* =========================================================
   ADICIONAIS FORMATADOS
========================================================= */

function formatarAdicionais(item) {

    if (
        !Array.isArray(item.adicionais) ||
        item.adicionais.length === 0
    ) {

        return 'Sem adicionais';
    }


    return item.adicionais
        .map(adicional =>
            escaparHTML(
                adicional.nome || ''
            )
        )
        .join(', ');
}


/* =========================================================
   ALTERAR QUANTIDADE
========================================================= */

function alterarQuantidadeCarrinho(
    indice,
    variacao
) {

    if (!carrinho[indice]) {
        return;
    }


    carrinho[indice].quantidade =
        (
            Number(
                carrinho[indice].quantidade
            ) || 1
        ) + variacao;


    if (
        carrinho[indice].quantidade <= 0
    ) {

        carrinho.splice(
            indice,
            1
        );
    }


    salvarCarrinho();

    renderizarCarrinho();
}


/* =========================================================
   IR PARA CHECKOUT
========================================================= */

function irParaCheckout() {

    if (carrinho.length === 0) {

        mostrarToast(
            'Seu carrinho está vazio.',
            'erro'
        );

        return;
    }


    if (!lojaAbertaGlobal) {

        /*
        Não bloqueamos obrigatoriamente o checkout
        caso a regra do estabelecimento permita
        agendamento futuro.
        */

        const continuar =
            confirm(
                'A loja está fechada neste momento. Deseja continuar mesmo assim?'
            );


        if (!continuar) {
            return;
        }
    }


    window.location.href =
        'checkout.html';
}


/* =========================================================
   INICIALIZAR CHECKOUT
========================================================= */

async function inicializarCheckout() {

    renderizarResumoCheckout();

    configurarPagamento();

    aplicarMascaraTelefone();

    await carregarBairros();
}


/* =========================================================
   RENDERIZAR RESUMO DO CHECKOUT
========================================================= */

function renderizarResumoCheckout() {

    const container =
        document.getElementById(
            'resumoItens'
        );


    if (!container) {
        return;
    }


    if (carrinho.length === 0) {

        container.innerHTML = `
            <div class="summary-empty">
                <div>🛒</div>
                <p>Seu carrinho está vazio.</p>

                <button
                    type="button"
                    onclick="voltarParaCardapio()"
                >
                    Voltar ao cardápio
                </button>
            </div>
        `;


        const btn =
            document.getElementById(
                'btnFinalizar'
            );


        if (btn) {
            btn.disabled = true;
        }


        atualizarTotaisCheckout();

        return;
    }


    container.innerHTML = '';


    carrinho.forEach(
        (item, indice) => {

            const adicionais =
                Array.isArray(item.adicionais)
                    ? item.adicionais
                    : [];


            const adicionaisTexto =
                adicionais.length > 0
                    ? adicionais
                        .map(a =>
                            a.nome || ''
                        )
                        .join(', ')
                    : 'Sem adicionais';


            const div =
                document.createElement(
                    'div'
                );


            div.className =
                'summary-item';


            div.innerHTML = `

                <div class="summary-item-top">

                    <div class="summary-item-quantity">
                        ${Number(item.quantidade) || 1}
                    </div>

                    <div class="summary-item-info">

                        <strong>
                            ${escaparHTML(
                                item.nome ||
                                'Açaí'
                            )}
                        </strong>

                        <span>
                            ${escaparHTML(
                                item.tamanhoNome ||
                                ''
                            )}
                        </span>

                        <small>
                            ${escaparHTML(
                                adicionaisTexto
                            )}
                        </small>

                    </div>

                    <strong class="summary-item-price">
                        ${formatarMoeda(
                            calcularTotalItem(item)
                        )}
                    </strong>

                </div>

                ${
                    item.observacao
                        ? `
                        <div class="summary-observation">
                            📝 ${escaparHTML(
                                item.observacao
                            )}
                        </div>
                        `
                        : ''
                }

                <div class="summary-item-actions">

                    <button
                        type="button"
                        onclick="alterarQuantidadeCheckout(${indice}, -1)"
                    >
                        −
                    </button>

                    <span>
                        ${Number(item.quantidade) || 1}
                    </span>

                    <button
                        type="button"
                        onclick="alterarQuantidadeCheckout(${indice}, 1)"
                    >
                        +
                    </button>

                    <button
                        type="button"
                        class="remove-item"
                        onclick="removerItemCheckout(${indice})"
                    >
                        Remover
                    </button>

                </div>
            `;


            container.appendChild(div);
        }
    );


    atualizarTotaisCheckout();
}


/* =========================================================
   ALTERAR QUANTIDADE NO CHECKOUT
========================================================= */

function alterarQuantidadeCheckout(
    indice,
    variacao
) {

    if (!carrinho[indice]) {
        return;
    }


    carrinho[indice].quantidade =
        (
            Number(
                carrinho[indice].quantidade
            ) || 1
        ) + variacao;


    if (
        carrinho[indice].quantidade <= 0
    ) {

        carrinho.splice(
            indice,
            1
        );
    }


    salvarCarrinho();

    renderizarResumoCheckout();
}


/* =========================================================
   REMOVER ITEM
========================================================= */

function removerItemCheckout(indice) {

    if (!carrinho[indice]) {
        return;
    }


    carrinho.splice(
        indice,
        1
    );


    salvarCarrinho();

    renderizarResumoCheckout();
}


/* =========================================================
   TOTAL CHECKOUT
========================================================= */

function atualizarTotaisCheckout() {

    const subtotal =
        calcularSubtotalCarrinho();


    /*
    A taxa de entrega vem do bairro.
    */

    const selectBairro =
        document.getElementById(
            'bairro'
        );


    let taxaEntrega = 0;


    if (
        selectBairro &&
        selectBairro.selectedOptions.length
    ) {

        const opcao =
            selectBairro.selectedOptions[0];


        taxaEntrega =
            Number(
                opcao.dataset.taxa
            ) || 0;
    }


    const total =
        subtotal +
        taxaEntrega;


    const subtotalEl =
        document.getElementById(
            'resumoSubtotal'
        );


    const entregaEl =
        document.getElementById(
            'resumoEntrega'
        );


    const totalEl =
        document.getElementById(
            'resumoTotal'
        );


    if (subtotalEl) {

        subtotalEl.textContent =
            formatarMoeda(subtotal);
    }


    if (entregaEl) {

        entregaEl.textContent =
            formatarMoeda(taxaEntrega);
    }


    if (totalEl) {

        totalEl.textContent =
            formatarMoeda(total);
    }
}


/* =========================================================
   CARREGAR BAIRROS
========================================================= */

async function carregarBairros() {

    const select =
        document.getElementById(
            'bairro'
        );


    if (!select) {
        return;
    }


    try {

        const resposta =
            await fetch(
                `${API}/bairros.php`,
                {
                    method: 'GET',
                    headers: {
                        'Accept':
                            'application/json'
                    }
                }
            );


        const dados =
            await resposta.json();


        if (!resposta.ok) {

            throw new Error(
                dados?.mensagem ||
                'Erro ao carregar bairros.'
            );
        }


        const bairros =
            dados.dados ||
            dados.bairros ||
            dados ||
            [];


        if (!Array.isArray(bairros)) {

            throw new Error(
                'Formato de bairros inválido.'
            );
        }


        bairros.forEach(
            bairro => {

                const option =
                    document.createElement(
                        'option'
                    );


                option.value =
                    bairro.id;


                option.textContent =
                    bairro.nome;


                option.dataset.taxa =
                    bairro.taxa || 0;


                select.appendChild(
                    option
                );
            }
        );


        select.addEventListener(
            'change',
            atualizarTotaisCheckout
        );


    } catch (erro) {

        console.error(
            'Erro ao carregar bairros:',
            erro
        );


        mostrarToast(
            'Não foi possível carregar os bairros.',
            'erro'
        );
    }
}


/* =========================================================
   PAGAMENTO
========================================================= */

function configurarPagamento() {

    const radios =
        document.querySelectorAll(
            'input[name="pagamento"]'
        );


    const areaTroco =
        document.getElementById(
            'areaTroco'
        );


    radios.forEach(
        radio => {

            radio.addEventListener(
                'change',
                () => {

                    if (
                        areaTroco
                    ) {

                        if (
                            radio.value ===
                            'dinheiro' &&
                            radio.checked
                        ) {

                            areaTroco.classList.remove(
                                'hidden'
                            );

                        } else {

                            areaTroco.classList.add(
                                'hidden'
                            );
                        }
                    }
                }
            );
        }
    );
}


/* =========================================================
   MÁSCARA TELEFONE
========================================================= */

function aplicarMascaraTelefone() {

    const input =
        document.getElementById(
            'clienteTelefone'
        );


    if (!input) {
        return;
    }


    input.addEventListener(
        'input',
        () => {

            let valor =
                input.value.replace(
                    /\D/g,
                    ''
                );


            valor =
                valor.substring(
                    0,
                    11
                );


            if (
                valor.length <= 10
            ) {

                valor =
                    valor.replace(
                        /^(\d{2})(\d)/,
                        '($1) $2'
                    );

                valor =
                    valor.replace(
                        /(\d{4})(\d)/,
                        '$1-$2'
                    );

            } else {

                valor =
                    valor.replace(
                        /^(\d{2})(\d)/,
                        '($1) $2'
                    );

                valor =
                    valor.replace(
                        /(\d{5})(\d)/,
                        '$1-$2'
                    );
            }


            input.value =
                valor;
        }
    );
}


/* =========================================================
   FINALIZAR PEDIDO
========================================================= */

async function finalizarPedido() {

    if (carrinho.length === 0) {

        mostrarToast(
            'Seu carrinho está vazio.',
            'erro'
        );

        return;
    }


    /*
    ---------------------------------------------------------
    VALIDAR CLIENTE
    ---------------------------------------------------------
    */

    const nome =
        document
            .getElementById(
                'clienteNome'
            )
            ?.value
            .trim();


    const telefone =
        document
            .getElementById(
                'clienteTelefone'
            )
            ?.value
            .trim();


    const email =
        document
            .getElementById(
                'clienteEmail'
            )
            ?.value
            .trim();


    if (!nome) {

        mostrarToast(
            'Informe seu nome.',
            'erro'
        );

        focar('clienteNome');

        return;
    }


    if (!telefone || telefone.replace(/\D/g, '').length < 10) {

        mostrarToast(
            'Informe um WhatsApp válido.',
            'erro'
        );

        focar('clienteTelefone');

        return;
    }


    if (
        !email ||
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
            email
        )
    ) {

        mostrarToast(
            'Informe um e-mail válido.',
            'erro'
        );

        focar('clienteEmail');

        return;
    }


    /*
    ---------------------------------------------------------
    ENDEREÇO
    ---------------------------------------------------------
    */

    const bairro =
        document
            .getElementById(
                'bairro'
            )
            ?.value;


    const endereco =
        document
            .getElementById(
                'endereco'
            )
            ?.value
            .trim();


    const numero =
        document
            .getElementById(
                'numero'
            )
            ?.value
            .trim();


    const complemento =
        document
            .getElementById(
                'complemento'
            )
            ?.value
            .trim();


    const referencia =
        document
            .getElementById(
                'referencia'
            )
            ?.value
            .trim();


    if (!bairro) {

        mostrarToast(
            'Selecione seu bairro.',
            'erro'
        );

        focar('bairro');

        return;
    }


    if (!endereco) {

        mostrarToast(
            'Informe sua rua ou avenida.',
            'erro'
        );

        focar('endereco');

        return;
    }


    if (!numero) {

        mostrarToast(
            'Informe o número.',
            'erro'
        );

        focar('numero');

        return;
    }


    /*
    ---------------------------------------------------------
    PAGAMENTO
    ---------------------------------------------------------
    */

    const pagamentoSelecionado =
        document.querySelector(
            'input[name="pagamento"]:checked'
        );


    if (!pagamentoSelecionado) {

        mostrarToast(
            'Escolha uma forma de pagamento.',
            'erro'
        );

        return;
    }


    const metodoPagamento =
        pagamentoSelecionado.value;


    let trocoPara = null;


    if (
        metodoPagamento ===
        'dinheiro'
    ) {

        const campoTroco =
            document.getElementById(
                'trocoPara'
            );


        if (
            campoTroco &&
            campoTroco.value !== ''
        ) {

            trocoPara =
                Number(
                    campoTroco.value
                );
        }
    }


    /*
    ---------------------------------------------------------
    OBSERVAÇÃO
    ---------------------------------------------------------
    */

    const observacaoGeral =
        document
            .getElementById(
                'observacaoPedido'
            )
            ?.value
            .trim() || '';


    /*
    ---------------------------------------------------------
    BUSCAR CLIENTE
     
    O pedidos.php precisa de cliente_id.
     
    Por isso, primeiro criamos/localizamos
    o cliente.
    ---------------------------------------------------------
    */

    const botao =
        document.getElementById(
            'btnFinalizar'
        );


    bloquearBotao(
        botao,
        'Processando pedido...'
    );


    try {

        /*
        =====================================================
         CRIAR/LOCALIZAR CLIENTE
        =====================================================
        */

        const clienteId =
            await obterClienteId({
                nome,
                telefone,
                email,
                endereco,
                numero,
                complemento,
                referencia
            });


        if (!clienteId) {

            throw new Error(
                'Não foi possível identificar o cliente.'
            );
        }


        /*
        =====================================================
         TRANSFORMAR CARRINHO
         
         NÃO enviamos preços.
        =====================================================
        */

        const itens =
            montarItensParaAPI(
                observacaoGeral
            );


        /*
        =====================================================
         CALCULAR TROCO
        =====================================================
        */

        if (
            metodoPagamento ===
            'dinheiro'
        ) {

            const total =
                calcularSubtotalCarrinho() +
                obterTaxaEntregaSelecionada();


            if (
                trocoPara !== null &&
                trocoPara < total
            ) {

                throw new Error(
                    'O valor para troco deve ser maior ou igual ao total do pedido.'
                );
            }
        }


        /*
        =====================================================
         CRIAR PEDIDO
        =====================================================
        */

        const respostaPedido =
            await fetch(
                `${API}/pedidos.php`,
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json'
                    },

                    body: JSON.stringify({

                        cliente_id:
                            clienteId,

                        metodo_pagamento:
                            metodoPagamento,

                        troco_para:
                            trocoPara,

                        /*
                        O total não é necessário.
                        O PHP calcula o total real.
                        */

                        itens:
                            itens
                    })
                }
            );


        const dadosPedido =
            await respostaPedido.json();


        if (
            !respostaPedido.ok ||
            !dadosPedido.sucesso
        ) {

            throw new Error(
                dadosPedido?.mensagem ||
                'Não foi possível criar o pedido.'
            );
        }


        const pedidoId =
            dadosPedido.pedido_id;


        /*
        =====================================================
         PIX
        =====================================================
        */

        if (
            metodoPagamento ===
            'pix'
        ) {

            await gerarPix(
                pedidoId,
                dadosPedido.total
            );


            desbloquearBotao(
                botao,
                'Finalizar pedido'
            );


            return;
        }


        /*
        =====================================================
         DINHEIRO / CARTÃO
        =====================================================
        */

        mostrarPedidoSucesso(
            pedidoId
        );


        /*
        Limpar carrinho depois de
        confirmar criação do pedido.
        */

        carrinho = [];

        salvarCarrinho();


    } catch (erro) {

        console.error(
            'Erro ao finalizar pedido:',
            erro
        );


        mostrarToast(
            erro.message ||
            'Não foi possível finalizar o pedido.',
            'erro'
        );


        desbloquearBotao(
            botao,
            'Finalizar pedido'
        );
    }
}


/* =========================================================
   MONTAR ITENS PARA API
========================================================= */

function montarItensParaAPI(
    observacaoGeral
) {

    return carrinho.map(
        item => {

            /*
            -------------------------------------------------
            TAMANHO
            -------------------------------------------------
            */

            const produtoTamanhoId =
                Number(
                    item.tamanhoId ||
                    item.produto_tamanho_id
                );


            /*
            -------------------------------------------------
            OBSERVAÇÃO
            -------------------------------------------------
            */

            let observacoes =
                item.observacao ||
                '';


            if (
                observacaoGeral
            ) {

                if (observacoes) {

                    observacoes +=
                        ' | ';

                }

                observacoes +=
                    observacaoGeral;
            }


            /*
            -------------------------------------------------
            ADICIONAIS
            -------------------------------------------------
            */

            const adicionais =
                Array.isArray(
                    item.adicionais
                )
                    ? item.adicionais
                        .map(
                            adicional => {

                                const id =
                                    Number(
                                        adicional.id ||
                                        adicional.adicional_id
                                    );


                                /*
                                Quantidade:
                                se não existir, usamos 1.
                                */

                                let quantidade =
                                    Number(
                                        adicional.quantidade
                                    );


                                if (
                                    !quantidade ||
                                    quantidade <= 0
                                ) {

                                    /*
                                    O formato antigo do
                                    carrinho coloca algo
                                    como "2x Morango"
                                    no nome.

                                    Tentamos recuperar
                                    a quantidade.
                                    */

                                    const texto =
                                        String(
                                            adicional.nome ||
                                            ''
                                        );


                                    const match =
                                        texto.match(
                                            /^(\d+)x\s/
                                        );


                                    quantidade =
                                        match
                                            ? Number(
                                                match[1]
                                            )
                                            : 1;
                                }


                                return {

                                    adicional_id:
                                        id,

                                    quantidade:
                                        quantidade
                                };
                            }
                        )
                        .filter(
                            adicional =>
                                adicional.adicional_id > 0
                        )
                    : [];


            return {

                produto_tamanho_id:
                    produtoTamanhoId,

                quantidade:
                    Number(
                        item.quantidade
                    ) || 1,

                observacoes:
                    observacoes ||
                    null,

                adicionais:
                    adicionais
            };
        }
    );
}


/* =========================================================
   OBTER CLIENTE ID
========================================================= */

async function obterClienteId(
    cliente
) {

    /*
    Endpoint esperado:
    
    /api/clientes.php
    
    Ele deverá criar ou localizar o cliente
    pelo telefone.

    */

    const resposta =
        await fetch(
            `${API}/clientes.php`,
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/json',

                    'Accept':
                        'application/json'
                },

                body:
                    JSON.stringify(
                        cliente
                    )
            }
        );


    const dados =
        await resposta.json();


    if (
        !resposta.ok ||
        !dados.sucesso
    ) {

        throw new Error(
            dados?.mensagem ||
            'Não foi possível cadastrar seus dados.'
        );
    }


    return Number(
        dados.cliente_id ||
        dados.id
    );
}


/* =========================================================
   GERAR PIX
========================================================= */

async function gerarPix(
    pedidoId,
    total
) {

    try {

        const resposta =
            await fetch(
                `${API}/gerar_pix.php`,
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json'
                    },

                    body:
                        JSON.stringify({

                            pedido_id:
                                pedidoId
                        })
                }
            );


        const dados =
            await resposta.json();


        if (
            !resposta.ok ||
            !dados.sucesso
        ) {

            throw new Error(
                dados?.mensagem ||
                'Não foi possível gerar o PIX.'
            );
        }


        /*
        -----------------------------------------------------
        PREENCHER MODAL
        -----------------------------------------------------
        */

        const pedidoElement =
            document.getElementById(
                'pixPedidoId'
            );


        const valorElement =
            document.getElementById(
                'pixValor'
            );


        const codigoElement =
            document.getElementById(
                'pixCopiaCola'
            );


        if (pedidoElement) {

            pedidoElement.textContent =
                pedidoId;
        }


        if (valorElement) {

            valorElement.textContent =
                formatarMoeda(
                    Number(
                        total || 0
                    )
                );
        }


        if (codigoElement) {

            codigoElement.value =
                dados.qr_code || '';
        }


        /*
        -----------------------------------------------------
        QR CODE
        -----------------------------------------------------
        */

        const qr =
            document.getElementById(
                'pixQrCode'
            );


        if (
            qr &&
            dados.qr_code_base64
        ) {

            qr.src =
                dados.qr_code_base64
                    .startsWith(
                        'data:image'
                    )
                        ? dados.qr_code_base64
                        : `data:image/png;base64,${dados.qr_code_base64}`;
        }


        /*
        -----------------------------------------------------
        ABRIR MODAL
        -----------------------------------------------------
        */

        const modal =
            document.getElementById(
                'modalPix'
            );


        if (modal) {

            modal.classList.remove(
                'hidden'
            );
        }


        /*
        -----------------------------------------------------
        LIMPAR CARRINHO
        -----------------------------------------------------
        */

        carrinho = [];

        salvarCarrinho();


    } catch (erro) {

        console.error(
            'Erro ao gerar PIX:',
            erro
        );


        throw erro;
    }
}


/* =========================================================
   COPIAR PIX
========================================================= */

async function copiarPix() {

    const campo =
        document.getElementById(
            'pixCopiaCola'
        );


    if (!campo) {
        return;
    }


    const codigo =
        campo.value.trim();


    if (!codigo) {

        mostrarToast(
            'Código PIX indisponível.',
            'erro'
        );

        return;
    }


    try {

        await navigator.clipboard.writeText(
            codigo
        );

    } catch {

        campo.select();

        document.execCommand(
            'copy'
        );
    }


    const mensagem =
        document.getElementById(
            'pixMensagemCopiado'
        );


    if (mensagem) {

        mensagem.classList.remove(
            'hidden'
        );


        setTimeout(
            () => {

                mensagem.classList.add(
                    'hidden'
                );

            },
            2500
        );
    }


    const botao =
        document.getElementById(
            'btnCopiarPix'
        );


    if (botao) {

        const textoOriginal =
            botao.textContent;


        botao.textContent =
            'Copiado!';


        setTimeout(
            () => {

                botao.textContent =
                    textoOriginal;

            },
            2500
        );
    }
}


/* =========================================================
   FECHAR MODAL PIX
========================================================= */

function fecharModalPix() {

    const modal =
        document.getElementById(
            'modalPix'
        );


    if (modal) {

        modal.classList.add(
            'hidden'
        );
    }


    mostrarPedidoSucesso(
        document.getElementById(
            'pixPedidoId'
        )?.textContent
    );
}


/* =========================================================
   SUCESSO
========================================================= */

function mostrarPedidoSucesso(
    pedidoId
) {

    const elemento =
        document.getElementById(
            'sucessoPedidoId'
        );


    if (elemento) {

        elemento.textContent =
            pedidoId;
    }


    const modal =
        document.getElementById(
            'modalSucesso'
        );


    if (modal) {

        modal.classList.remove(
            'hidden'
        );
    }
}


/* =========================================================
   VOLTAR AO INÍCIO
========================================================= */

function voltarInicio() {

    window.location.href =
        'index.html';
}


/* =========================================================
   VOLTAR PARA CARDÁPIO
========================================================= */

function voltarParaCardapio() {

    window.location.href =
        'index.html';
}


/* =========================================================
   TAXA DE ENTREGA
========================================================= */

function obterTaxaEntregaSelecionada() {

    const select =
        document.getElementById(
            'bairro'
        );


    if (
        !select ||
        !select.selectedOptions.length
    ) {

        return 0;
    }


    return Number(
        select
            .selectedOptions[0]
            .dataset.taxa
    ) || 0;
}


/* =========================================================
   TOAST
========================================================= */

function mostrarToast(
    mensagem,
    tipo = 'info'
) {

    const toast =
        document.getElementById(
            'toast'
        );


    if (!toast) {

        alert(mensagem);

        return;
    }


    toast.textContent =
        mensagem;


    toast.className =
        `toast ${tipo}`;


    toast.classList.remove(
        'hidden'
    );


    clearTimeout(
        window.toastTimeout
    );


    window.toastTimeout =
        setTimeout(
            () => {

                toast.classList.add(
                    'hidden'
                );

            },
            3500
        );
}


/* =========================================================
   BLOQUEAR BOTÃO
========================================================= */

function bloquearBotao(
    botao,
    texto
) {

    if (!botao) {
        return;
    }


    botao.disabled =
        true;


    const textoEl =
        document.getElementById(
            'textoFinalizar'
        );


    if (textoEl) {

        textoEl.textContent =
            texto;
    }
}


/* =========================================================
   DESBLOQUEAR BOTÃO
========================================================= */

function desbloquearBotao(
    botao,
    texto
) {

    if (!botao) {
        return;
    }


    botao.disabled =
        false;


    const textoEl =
        document.getElementById(
            'textoFinalizar'
        );


    if (textoEl) {

        textoEl.textContent =
            texto;
    }
}


/* =========================================================
   FOCAR CAMPO
========================================================= */

function focar(id) {

    const elemento =
        document.getElementById(id);


    if (elemento) {

        elemento.focus();
    }
}


/* =========================================================
   ESCAPAR HTML
========================================================= */

function escaparHTML(
    valor
) {

    return String(
        valor ?? ''
    )
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}