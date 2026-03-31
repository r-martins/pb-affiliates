=== PB Afiliados ===
Contributors: martins56
Tags: woocommerce, afiliados, pagbank, comissões, programa de afiliados
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Transforme clientes e parceiros em uma rede de vendas: programa de afiliados completo para WooCommerce com PagBank — links, comissões flexíveis, repasse manual ou split automático e relatórios que mostram o que funciona.

== Description ==

**PB Afiliados** foi feito para lojas que querem crescer com indicações sem planilhas nem gambiarras. Você define as regras; o plugin rastreia cliques, atribui vendas, calcula comissões e dá ao afiliado uma área clara na conta — enquanto você mantém o controle total no painel WordPress.

= Por que escolher este plugin? =

* **Pronto para o Brasil** — Integração com **PagBank Connect** (split ou pagamento manual).
* **Flexível** — Comissão por loja, por categoria, por afiliado ou por cupom; percentual ou valor fixo.
* **Transparente** — Dashboard, relatórios e histórico para afiliados e para a área administrativa.
* **Profissional** — Rastreamento com cookie, identificador na URL e opção de atribuição por domínio (referer verificado).

= Crie um time de afiliados e turbine suas vendas =

* Mais conversão com **links de indicação** fáceis de copiar e compartilhar.
* **Montador de links**: o afiliado cola o URL de um produto, categoria ou página da loja e recebe o link já com o código — sem erro de cópia.
* Encurtamento opcional e gratuito
* **Materiais promocionais**: disponibilize arquivos para download na área do afiliado.
* **Gráficos e relatórios** pra você e seus afiliados crescerem juntos.
* **Relatórios avançados no admin** (incluindo análise de cliques e desempenho por afiliado).
* **E-mails transacionais** para cadastro, nova comissão e comissão paga (via WooCommerce).
* Modo **pagamento manual** (saques, comprovantes) ou **split automático PagBank** conforme a configuração da loja.
* Gere **cupons** com descontos e comissões personalizadas para afiliados
* Defina comissões diferentes **por categoria** e também **por afiliado**

= Requisitos =

* **WooCommerce** ativo  
* **PagBank Connect** ativo com pelo menos um método de pagamento disponível  
* **HPOS** (armazenamento de pedidos de alto desempenho) ativado na loja — requisito para ativação do plugin

Sem estes itens, o plugin não ativa ou não opera como esperado — é uma escolha deliberada para integração sólida com pagamentos e pedidos modernos.

== Installation ==

1. Envie a pasta `pb-affiliates` para `wp-content/plugins/`.
2. Confirme que WooCommerce está com **HPOS** habilitado e que o **PagBank Connect** está configurado.
3. Ative **PB Afiliados** no painel Plugins.
4. Ajuste **Afiliados → Configurações** (comissão padrão, cookie, modo de pagamento, etc.).
5. (Opcional) Defina uma página de termos e a política de cadastro de afiliados.

Após ativar, os afiliados passam a ver a área do programa em **Minha conta** (endpoints de afiliado).

== Frequently Asked Questions ==

= Preciso do PagBank mesmo usando só pagamento manual? =

Sim. O plugin foi desenhado em torno do ecossistema PagBank Connect; a exigência garante um caminho único de integração e compatibilidade com métodos de pagamento da loja.

= O afiliado precisa instalar algo? =

Não. Tudo roda na sua loja: conta do cliente, cookies para rastreamento e scripts compatíveis com cache de página quando necessário.

= Posso misturar regras de comissão? =

Sim. Há precedência clara: cupom de afiliado e regra no perfil do usuário podem sobrepor o cálculo “por linha” com categorias e taxa padrão da loja. Veja a secção técnica abaixo.

= O split substitui o saque manual? =

No modo split, o repasse segue as regras do conector PagBank; a interface de saques manuais e histórico de pagamentos refletem o que a loja registra — ideal para evitar expectativas erradas de quem só recebe via split.

== Regras de cálculo de comissão (referência técnica) ==

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

== Screenshots ==

1. Área do afiliado no WooCommerce — dashboard, métricas e ferramentas de divulgação.

== Changelog ==

= 1.0.0 =
* Versão inicial.
