let carrinho =
    JSON.parse(
        localStorage.getItem('carrinhoAcai')
    ) || [];


let cardapioGlobal = [];

let produtoSelecionado = null;

let categoriaSelecionada = '';

let adicionaisSelecionados = [];

let tamanhoSelecionado = null;

let lojaAbertaGlobal = true;


/* =====================================================
   ELEMENTOS
===================================================== */


const categoriasMenu =
    document.getElementById('categoriasMenu');


const cardapioContainer =
    document.getElementById('cardapioContainer');


const modalOverlay =
    document.getElementById('modalOverlay');


const modalProduto =
    document.getElementById('modalProduto');


const carrinhoDrawer =
    document.getElementById('carrinhoDrawer');


/* =====================================================
   HORÁRIO DA LOJA
===================================================== */


function verificarHorarioLoja() {

    const horaAbertura = 14;

    const horaFechamento = 23;


    const agora = new Date();

    const horaAtual =
        agora.getHours();


    const aberto =
        horaAtual >= horaAbertura &&
        horaAtual < horaFechamento;


    lojaAbertaGlobal = aberto;


    const status =
        document.getElementById('statusLoja');


    const ponto =
        document.getElementById('statusPonto');


    if (aberto) {

        status.innerText =
            'Loja aberta • Aceitando pedidos';


        ponto.className =
            'status-ponto aberto';

    } else {

        status.innerText =
            'Loja fechada no momento';


        ponto.className =
            'status-ponto fechado';

    }

}


/* =====================================================
   CARREGAR CARDÁPIO
===================================================== */


async function carregarCardapio() {

    try {

        const resposta =
            await fetch('/api/cardapio.php');


        if (!resposta.ok) {

            throw new Error(
                'Erro ao carregar cardápio'
            );

        }


        const cardapio =
            await resposta.json();


        cardapioGlobal =
            cardapio;


        renderizarCategorias(
            cardapio
        );


        renderizarCardapio(
            cardapio
        );


    } catch (erro) {

        console.error(
            'Erro:',
            erro
        );


        cardapioContainer.innerHTML = `

            <div class="carregando">

                <p>
                    😕 Não foi possível carregar o cardápio.
                </p>

                <button onclick="location.reload()">

                    Tentar novamente

                </button>

            </div>

        `;

    }

}


/* =====================================================
   CATEGORIAS
===================================================== */


function renderizarCategorias(cardapio) {

    categoriasMenu.innerHTML = '';


    cardapio.forEach(
        (categoria, index) => {


            const botao =
                document.createElement('button');


            botao.className =
                'categoria';


            if (index === 0) {

                botao.classList.add(
                    'ativa'
                );

            }


            botao.innerText =
                categoria.categoria;


            botao.addEventListener(
                'click',
                () => {

                    document
                        .querySelectorAll('.categoria')
                        .forEach(
                            btn =>
                                btn.classList.remove(
                                    'ativa'
                                )
                        );


                    botao.classList.add(
                        'ativa'
                    );


                    document
                        .getElementById(
                            `categoria-${categoria.id}`
                        )
                        ?.scrollIntoView({

                            behavior:
                                'smooth',

                            block:
                                'start'

                        });

                }
            );


            categoriasMenu.appendChild(
                botao
            );

        }
    );

}


/* =====================================================
   RENDERIZAR PRODUTOS
===================================================== */


function renderizarCardapio(cardapio) {

    cardapioContainer.innerHTML = '';


    cardapio.forEach(
        categoria => {


            const secao =
                document.createElement('section');


            secao.id =
                `categoria-${categoria.id}`;


            secao.innerHTML = `

                <h2 class="categoria-titulo">

                    ${categoria.categoria}

                </h2>

                <div
                    class="produtos-grid"
                    id="produtos-${categoria.id}"
                >

                </div>

            `;


            const grid =
                secao.querySelector(
                    '.produtos-grid'
                );


            categoria.produtos.forEach(
                produto => {


                    const precos =
                        produto.tamanhos.map(
                            tamanho =>
                                parseFloat(
                                    tamanho.preco
                                )
                        );


                    const menorPreco =
                        Math.min(
                            ...precos
                        );


                    const card =
                        document.createElement(
                            'article'
                        );


                    card.className =
                        'produto-card';


                    const emoji =
                        obterEmojiProduto(
                            produto.nome,
                            categoria.categoria
                        );


                    card.innerHTML = `

                        <div class="produto-visual">

                            ${emoji}

                        </div>


                        <div class="produto-info">

                            <h3>

                                ${produto.nome}

                            </h3>


                            <p>

                                ${produto.descricao || ''}

                            </p>


                            <div class="produto-footer">

                                <div class="produto-preco">

                                    <span>
                                        A partir de
                                    </span>

                                    <strong>

                                        ${formatarPreco(
                                            menorPreco
                                        )}

                                    </strong>

                                </div>


                                <button
                                    class="btn-adicionar"
                                    aria-label="Adicionar produto"
                                >

                                    +

                                </button>

                            </div>

                        </div>

                    `;


                    card.addEventListener(
                        'click',
                        () => {

                            abrirModalProduto(
                                produto,
                                categoria.categoria
                            );

                        }
                    );


                    grid.appendChild(
                        card
                    );

                }
            );


            cardapioContainer.appendChild(
                secao
            );

        }
    );

}


/* =====================================================
   EMOJI PRODUTO
===================================================== */


function obterEmojiProduto(
    nome,
    categoria
) {

    const texto =
        `${nome} ${categoria}`
        .toLowerCase();


    if (
        texto.includes('milk')
    ) {

        return '🥤';

    }


    if (
        texto.includes('sorvete')
    ) {

        return '🍦';

    }


    if (
        texto.includes('morango')
    ) {

        return '🍓';

    }


    if (
        texto.includes('banana')
    ) {

        return '🍌';

    }


    return '🥣';

}


/* =====================================================
   ABRIR MODAL
===================================================== */


async function abrirModalProduto(
    produto,
    categoria
) {

    produtoSelecionado =
        produto;


    categoriaSelecionada =
        categoria;


    tamanhoSelecionado =
        produto.tamanhos[0] || null;


    adicionaisSelecionados =
        [];


    document
        .getElementById(
            'modalTitulo'
        )
        .innerText =
        produto.nome;


    document
        .getElementById(
            'modalCategoria'
        )
        .innerText =
        categoria;


    document
        .getElementById(
            'modalDescricao'
        )
        .innerText =
        produto.descricao || '';


    document
        .getElementById(
            'modalObservacao'
        )
        .value = '';


    renderizarTamanhos();


    const aceitaComplementos =
        categoria
            .toLowerCase()
            .includes('açaí') ||

        categoria
            .toLowerCase()
            .includes('acai') ||

        categoria
            .toLowerCase()
            .includes('milkshake') ||

        categoria
            .toLowerCase()
            .includes('destaque') ||

        categoria
            .toLowerCase()
            .includes('mais vendido');


    const secaoAdicionais =
        document.getElementById(
            'secaoAdicionais'
        );


    if (aceitaComplementos) {

        secaoAdicionais.style.display =
            'block';


        await carregarAdicionais();

    } else {

        secaoAdicionais.style.display =
            'none';

    }


    atualizarTotalModal();


    modalOverlay.classList.remove(
        'escondido'
    );


    modalProduto.classList.remove(
        'escondido'
    );

}


/* =====================================================
   TAMANHOS
===================================================== */


function renderizarTamanhos() {

    const container =
        document.getElementById(
            'modalTamanhos'
        );


    container.innerHTML = '';


    produtoSelecionado.tamanhos.forEach(
        tamanho => {


            const opcao =
                document.createElement(
                    'div'
                );


            opcao.className =
                'opcao-tamanho';


            if (
                tamanhoSelecionado &&
                tamanhoSelecionado.id == tamanho.id
            ) {

                opcao.classList.add(
                    'selecionado'
                );

            }


            opcao.innerHTML = `

                <span>

                    ${tamanho.tamanho}

                </span>


                <strong>

                    ${formatarPreco(
                        tamanho.preco
                    )}

                </strong>

            `;


            opcao.addEventListener(
                'click',
                () => {

                    tamanhoSelecionado =
                        tamanho;


                    renderizarTamanhos();


                    atualizarTotalModal();

                }
            );


            container.appendChild(
                opcao
            );

        }
    );

}


/* =====================================================
   ADICIONAIS
===================================================== */


async function carregarAdicionais() {

    const container =
        document.getElementById(
            'modalAdicionais'
        );


    container.innerHTML =
        'Carregando adicionais...';


    try {

        const resposta =
            await fetch(
                '/api/adicionais.php'
            );


        const adicionais =
            await resposta.json();


        container.innerHTML = '';


        adicionais.forEach(
            adicional => {


                const card =
                    document.createElement(
                        'div'
                    );


                card.className =
                    'adicional-card';


                const preco =
                    parseFloat(
                        adicional.preco
                    );


                card.innerHTML = `

                    <div class="adicional-info">

                        <strong>

                            ${adicional.nome}

                        </strong>


                        <span>

                            ${
                                preco > 0
                                    ? '+ ' +
                                      formatarPreco(
                                          preco
                                      )
                                    : 'Grátis'
                            }

                        </span>

                    </div>


                    <div class="adicional-controle">

                        <button
                            type="button"
                            class="btn-menos"
                        >

                            −

                        </button>


                        <span>

                            0

                        </span>


                        <button
                            type="button"
                            class="btn-mais"
                        >

                            +

                        </button>

                    </div>

                `;


                let quantidade = 0;


                const btnMenos =
                    card.querySelector(
                        '.btn-menos'
                    );


                const btnMais =
                    card.querySelector(
                        '.btn-mais'
                    );


                const spanQtd =
                    card.querySelector(
                        '.adicional-controle span'
                    );


                function atualizarQuantidade() {

                    spanQtd.innerText =
                        quantidade;


                    const index =
                        adicionaisSelecionados.findIndex(
                            item =>
                                item.id ==
                                adicional.id
                        );


                    if (quantidade <= 0) {

                        if (index !== -1) {

                            adicionaisSelecionados.splice(
                                index,
                                1
                            );

                        }

                    } else {

                        const objeto = {

                            id:
                                adicional.id,

                            nome:
                                adicional.nome,

                            precoUnitario:
                                preco,

                            quantidade:
                                quantidade

                        };


                        if (index !== -1) {

                            adicionaisSelecionados[
                                index
                            ] =
                                objeto;

                        } else {

                            adicionaisSelecionados.push(
                                objeto
                            );

                        }

                    }


                    atualizarTotalModal();

                }


                btnMais.addEventListener(
                    'click',
                    () => {

                        quantidade++;

                        atualizarQuantidade();

                    }
                );


                btnMenos.addEventListener(
                    'click',
                    () => {

                        if (
                            quantidade > 0
                        ) {

                            quantidade--;

                            atualizarQuantidade();

                        }

                    }
                );


                container.appendChild(
                    card
                );

            }
        );

    } catch (erro) {

        console.error(
            erro
        );


        container.innerHTML = `

            <p>

                Não foi possível carregar os adicionais.

            </p>

        `;

    }

}


/* =====================================================
   TOTAL MODAL
===================================================== */


function atualizarTotalModal() {

    if (!tamanhoSelecionado) {

        return;

    }


    let total =
        parseFloat(
            tamanhoSelecionado.preco
        );


    adicionaisSelecionados.forEach(
        adicional => {

            total +=
                adicional.precoUnitario *
                adicional.quantidade;

        }
    );


    document
        .getElementById(
            'modalTotal'
        )
        .innerText =
        formatarPreco(
            total
        );

}


/* =====================================================
   ADICIONAR AO CARRINHO
===================================================== */


document
    .getElementById(
        'btnAdicionarCarrinho'
    )
    .addEventListener(
        'click',
        () => {


            if (
                !produtoSelecionado ||
                !tamanhoSelecionado
            ) {

                return;

            }


            let valorAdicionais = 0;


            const adicionaisCarrinho =
                adicionaisSelecionados.map(
                    adicional => {


                        const precoTotal =
                            adicional.precoUnitario *
                            adicional.quantidade;


                        valorAdicionais +=
                            precoTotal;


                        return {

                id:
                    adicional.id,

                nome:
                    adicional.nome,

                quantidade:
                    adicional.quantidade,

                precoUnitario:
                    adicional.precoUnitario,

                preco:
                    precoTotal

            };


                    }
                );


            const precoTotal =

                parseFloat(
                    tamanhoSelecionado.preco
                ) +

                valorAdicionais;


            const item = {

                produtoId:
                    produtoSelecionado.id,

                nome:
                    produtoSelecionado.nome,

                tamanhoId:
                    tamanhoSelecionado.id,

                tamanhoNome:
                    tamanhoSelecionado.tamanho,

                adicionais:
                    adicionaisCarrinho,

                observacao:

                    document
                        .getElementById(
                            'modalObservacao'
                        )
                        .value
                        .trim(),

                precoTotal:
                    precoTotal,

                quantidade:
                    1

            };


            carrinho.push(
                item
            );


            salvarCarrinho();


            fecharModal();


            atualizarCarrinho();


        }
    );


/* =====================================================
   FECHAR MODAL
===================================================== */


function fecharModal() {

    modalOverlay.classList.add(
        'escondido'
    );


    modalProduto.classList.add(
        'escondido'
    );

}


document
    .getElementById(
        'btnFecharModal'
    )
    .addEventListener(
        'click',
        fecharModal
    );


modalOverlay.addEventListener(
    'click',
    fecharModal
);


/* =====================================================
   CARRINHO
===================================================== */


function salvarCarrinho() {

    localStorage.setItem(
        'carrinhoAcai',
        JSON.stringify(
            carrinho
        )
    );

}


function atualizarCarrinho() {

    const quantidade =
        carrinho.reduce(
            (
                total,
                item
            ) =>

                total +
                (
                    item.quantidade || 1
                ),

            0
        );


    const total =
        carrinho.reduce(
            (
                soma,
                item
            ) =>

                soma +
                item.precoTotal,

            0
        );


    document
        .getElementById(
            'contadorCarrinho'
        )
        .innerText =
        quantidade;


    document
        .getElementById(
            'barraQuantidade'
        )
        .innerText =
        `${quantidade} ${
            quantidade === 1
                ? 'item'
                : 'itens'
        }`;


    document
        .getElementById(
            'barraTotal'
        )
        .innerText =
        formatarPreco(
            total
        );


    const barra =
        document.getElementById(
            'barraCarrinho'
        );


    if (
        carrinho.length > 0
    ) {

        barra.classList.remove(
            'escondido'
        );

    } else {

        barra.classList.add(
            'escondido'
        );

    }


    renderizarDrawerCarrinho();

}


/* =====================================================
   DRAWER CARRINHO
===================================================== */


function renderizarDrawerCarrinho() {

    const container =
        document.getElementById(
            'drawerItens'
        );


    container.innerHTML = '';


    if (
        carrinho.length === 0
    ) {

        container.innerHTML = `

            <p style="
                text-align:center;
                color:#777;
                margin-top:50px;
            ">

                Seu carrinho está vazio 🥣

            </p>

        `;

    }


    carrinho.forEach(
        (
            item,
            index
        ) => {


            const adicionais =
                item.adicionais?.length
                    ? item.adicionais
                        .map(
                            a =>
                                a.nome
                        )
                        .join(', ')
                    : 'Sem adicionais';


            const div =
                document.createElement(
                    'div'
                );


            div.className =
                'drawer-item';


            div.innerHTML = `

                <h4>

                    ${item.quantidade || 1}x
                    ${item.nome}

                </h4>


                <p>

                    ${item.tamanhoNome}

                </p>


                <p>

                    ${adicionais}

                </p>


                <div
                    class="drawer-item-footer"
                >

                    <strong>

                        ${formatarPreco(
                            item.precoTotal
                        )}

                    </strong>


                    <button
                        class="btn-remover"
                    >

                        Remover

                    </button>

                </div>

            `;


            div
                .querySelector(
                    '.btn-remover'
                )
                .addEventListener(
                    'click',
                    () => {

                        carrinho.splice(
                            index,
                            1
                        );


                        salvarCarrinho();


                        atualizarCarrinho();

                    }
                );


            container.appendChild(
                div
            );

        }
    );


    const total =
        carrinho.reduce(
            (
                soma,
                item
            ) =>

                soma +
                item.precoTotal,

            0
        );


    document
        .getElementById(
            'drawerTotal'
        )
        .innerText =
        formatarPreco(
            total
        );

}


/* =====================================================
   ABRIR CARRINHO
===================================================== */


function abrirCarrinho() {

    renderizarDrawerCarrinho();


    carrinhoDrawer.classList.remove(
        'escondido'
    );

}


function fecharCarrinho() {

    carrinhoDrawer.classList.add(
        'escondido'
    );

}


document
    .getElementById(
        'btnAbrirCarrinho'
    )
    .addEventListener(
        'click',
        abrirCarrinho
    );


document
    .getElementById(
        'btnFecharCarrinho'
    )
    .addEventListener(
        'click',
        fecharCarrinho
    );


document
    .getElementById(
        'barraCarrinho'
    )
    .addEventListener(
        'click',
        abrirCarrinho
    );


/* =====================================================
   CHECKOUT
===================================================== */


function irParaCheckout() {

    if (
        carrinho.length === 0
    ) {

        return;

    }


    window.location.href =
        'checkout.html';

}


document
    .getElementById(
        'btnIrCheckout'
    )
    .addEventListener(
        'click',
        event => {

            event.stopPropagation();

            irParaCheckout();

        }
    );


document
    .getElementById(
        'btnFinalizarDrawer'
    )
    .addEventListener(
        'click',
        irParaCheckout
    );


/* =====================================================
   HERO
===================================================== */


document
    .getElementById(
        'btnExplorarCardapio'
    )
    .addEventListener(
        'click',
        () => {

            document
                .getElementById(
                    'areaCardapio'
                )
                .scrollIntoView({

                    behavior:
                        'smooth'

                });

        }
    );


/* =====================================================
   PREÇO
===================================================== */


function formatarPreco(valor) {

    return Number(
        valor
    ).toLocaleString(
        'pt-BR',
        {

            style:
                'currency',

            currency:
                'BRL'

        }
    );

}


/* =====================================================
   INICIALIZAÇÃO
===================================================== */


document.addEventListener(
    'DOMContentLoaded',
    () => {

        verificarHorarioLoja();

        carregarCardapio();

        atualizarCarrinho();

    }
);