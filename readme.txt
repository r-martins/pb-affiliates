=== PB Afiliados ===
Contributors: martins56
Tags: woocommerce, affiliates, pagbank, commission
Requires at least: 5.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Programa de afiliados para WooCommerce com integração PagBank Connect.

== Description ==

Exige WooCommerce e PagBank Connect. Permite códigos de afiliado, comissões, split ou pagamento manual e relatórios. Para ativar, a loja deve usar HPOS (armazenamento de pedidos de alto desempenho).

== Regras de cálculo de comissão ==

A comissão só é calculada para pedidos atribuídos a um afiliado **ativo** e quando há base de cálculo válida.

* **Base de cálculo**  
  É derivada do subtotal dos itens do pedido, conforme as opções em Afiliados → Configurações: podem ser descontadas **taxas (fees)** do pedido. O frete não entra no subtotal dos itens. A base nunca fica negativa.

* **Precedência da regra de taxa (qual percentual ou valor fixo usar)**  
  1. **Cupom de afiliado** — Se o pedido tiver cupom ligado ao programa com tipo e valor de comissão definidos no cupom, usa-se essa regra para **todo o pedido** (uma única taxa).  
  2. **Perfil do afiliado** — Se o administrador tiver definido comissão personalizada no utilizador (tipo + valor), usa-se essa regra para **todo o pedido**.  
  3. **Caso contrário** — Aplica-se o modo **por linha de item** (ver abaixo), usando a comissão **padrão da loja** como valor de referência quando nenhuma categoria impõe regra àquele produto.

* **Modo por linha (sem cupom e sem comissão no perfil do afiliado)**  
  - A base total do pedido é **repartida proporcionalmente** pelo subtotal de cada linha de produto.  
  - Para cada linha, determina-se uma taxa **(percentual ou valor fixo)** a aplicar à **base alocada àquela linha**.  
  - O produto considerado para categorias é o **pai** se o item for uma **variação**.  
  - **Categoria de produto:** nas categorias que tiverem comissão própria (Produtos → Categorias → secção PB Afiliados), recolhem-se todas as regras aplicáveis ao produto. Se existir mais do que uma categoria com regra, **prevalece a regra que produzir a menor comissão monetária** para a base **daquela linha** (compara-se percentual e fixo sobre essa base).  
  - Se o produto **não** tiver nenhuma categoria com regra personalizada, usa-se a **comissão padrão** definida em Afiliados → Configurações (tipo + valor).  
  - A comissão final do pedido é a **soma** das comissões calculadas por linha. Se as linhas não partilharem a mesma taxa, o sistema pode registar o tipo como combinação mista e um percentual equivalente sobre a base total (para referência em relatórios).

* **Onde configurar**  
  - **Padrão da loja:** Afiliados → Configurações (tipo e valor).  
  - **Por categoria:** editar cada categoria em Produtos → Categorias.  
  - **Por afiliado:** perfil do utilizador no admin (quando existir).  
  - **Por cupom:** metadados do cupom de afiliado no WooCommerce.

== Installation ==

1. Envie a pasta `pb-affiliates` para `wp-content/plugins/`.
2. Ative o plugin no painel do WordPress.

== Changelog ==

= 1.0.0 =
* Versão inicial.
