# Gestão de Orçamentos para Impressão 3D baseado em upload de G-code
Um sistema simples criado com o Trae em php+mysql para gerenciamento de orçamentos de impressão 3D.

O sistema é uma aplicação web simples, criada com IA do TRAE, mas muito funcional. É focada exclusivamente em gerar orçamentos automáticos de impressão 3D com base nos parâmetros cadastrados pelo operador e nas informações extraídas de um arquivo G-code enviado pelo usuário.
O objetivo é agilizar o cálculo do custo final da impressão, identificando automaticamente quantidade de material por cor/extrusor, tempo total de impressão e aplicando regras internas de precificação definidas previamente pelo operador.

Fluxo Geral

O usuário acessa a página e faz o upload do G-code.
O sistema interpreta o G-code e calcula:
Tempo estimado de impressão
Quantidade de material extrudado por filamento/cor (E total separado por extrusor: E0, E1, E2…)
O operador cadastra ou ajusta previamente os parâmetros:
Custo por grama para cada tipo/cor de filamento
Custo de energia (R$/kWh)
Margem de lucro (%)
Custo hora-máquina (opcional)
Valor fixo de preparação (opcional)
O sistema cruza dados do G-code com valores cadastrados, calcula o orçamento e exibe o total.
O usuário pode imprimir, baixar ou copiar o resumo do orçamento.

O operador define previamente:
Custo por grama (por material/cor) -	Base para cálculo do custo do filamento
Custo kWh - 	Usado para calcular o custo energético
Potência média da impressora (W) -	Para estimar consumo elétrico
Margem de lucro (%)	Aplicada sobre o subtotal
Taxas adicionais de serviços (opcional)

Telas do sistema:


