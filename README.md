Manager System

- Apenas para usuários root;
- Este é a administração para todos os outros sistemas.

# Regras de programação do projeto

Este documento registra as convenções de desenvolvimento que devem ser seguidas neste projeto.

## Idioma e nomenclatura

- Mantenha comentários de métodos e documentação em inglês.
- Escreva nomes de métodos em inglês.
- Use `camelCase` em nomes de métodos quando a convenção do PHP/framework ao redor pedir esse padrão.
- Prefira nomes claros para models, controllers e services, sempre alinhados com a entidade de domínio.
- Mantenha rótulos e textos visíveis ao usuário em português quando a view atual já estiver em português.

## Estrutura PHP

- Mantenha actions de controllers enxutas: valide permissões, leia entradas, chame models/services e escolha a resposta.
- Coloque escritas no banco e fluxos transacionais em models ou services.
- Reutilize helpers existentes em `app/Helpers` antes de criar novas lógicas de formatação ou parsing.
- Use `requirePermission()` ou `requireAnyPermission()` em actions que expõem dados protegidos.
- Retorne JSON com `$this->json()` em endpoints AJAX.
- Evite refatorações sem relação direta com a alteração solicitada.

## Views e assets

- Para arquivos de view PHP, coloque JavaScript específico da tela em:
  `public/assets/js/{nome_plural_da_view}/{basename_da_view}.js`
- Para arquivos de view PHP, coloque CSS específico da tela em:
  `public/assets/css/{nome_plural_da_view}/{basename_da_view}.css`
- Exemplo: `app/Views/people/index.php` deve usar:
  `public/assets/js/people/index.js` e `public/assets/css/people/index.css`
- Inclua JS e CSS específicos da view com query string de cache:
  `?v={numero}`
- Escape saídas em views com `htmlspecialchars()` ou com o padrão local de helper `$e()`.
- Mantenha nomes de campos agrupados por entidade, como `person[name]`, `user[type]`, `emails[0][address]`.

## Regras para SQL

- Sempre use prepared statements e placeholders nomeados para valores dinâmicos.
- Não concatene entradas do usuário diretamente em strings SQL.
- Use listas explícitas de colunas; evite `SELECT *` em queries usadas por telas ou APIs.
- Use aliases legíveis em PascalCase representando a tabela/entidade, como `Address`, `Neighborhood`, `City`, `State`.
- Coloque cada coluna selecionada em sua própria linha.
- Alinhe SQL multi-linha para que `FROM`, `JOIN`, `WHERE` e `ORDER BY` fiquem fáceis de escanear.
- Use tipos de join explícitos. Prefira `LEFT JOIN` apenas quando registros relacionados ausentes forem aceitáveis.
- Use `ORDER BY` explícito para garantir saída determinística em telas.

Exemplo recomendado de formatação SQL:

```php
$addressesStmt = $this->db->prepare("SELECT Address.id,
                                            Address.zip_code,
                                            Address.street,
                                            Address.number,
                                            Address.observations,
                                            Address.neighborhood_id,
                                            Neighborhood.name AS neighborhood_name,
                                            City.name AS city_name,
                                            State.name AS state_name
                                     FROM addresses AS Address
                                     LEFT JOIN neighborhoods AS Neighborhood ON Neighborhood.id = Address.neighborhood_id
                                     LEFT JOIN cities AS City ON City.id = Neighborhood.city_id
                                     LEFT JOIN states AS State ON State.id = City.state_id
                                     WHERE Address.person_id = :id
                                     ORDER BY Address.id ASC");
$addressesStmt->execute(['id' => $id]);
```

## Banco de dados e transações

- Para acesso a banco de dados em tarefas operacionais, debugging e consultas fora da aplicação web, use sempre o MCP do projeto.
- Não use acesso direto via clientes nativos (`mysql`, `psql`, etc.) quando a operação puder ser feita via MCP.
- Ao executar o MCP localmente, use sempre PHP na linha de comando (ex.: `php .mcp/server.php` ou `./.mcp/run.sh`).
- Envolva fluxos de create/update/delete em múltiplas tabelas com transações.
- Faça rollback da transação quando qualquer escrita falhar.
- Mantenha relacionamentos de tenants explícitos por meio de `tenant_users`.
- Ao atualizar registros filhos repetíveis, valide tanto o `id` quanto a chave estrangeira do dono quando possível, por exemplo `id` + `person_id`.
- Normalize documentos, datas e telefones com helpers existentes antes de persistir.

## Comportamento frontend

- Siga os padrões existentes de UI baseados em Bootstrap.
- Use Bootstrap Icons já utilizados pelo projeto em botões de ação.
- Mantenha blocos repetíveis visualmente consistentes com o padrão `people-repeat-item`.
- Para modais, envie formulários AJAX com `fetch()` e inclua:
  `Accept: application/json` e `X-Requested-With: XMLHttpRequest`.
- Mantenha controles desabilitados ou somente leitura quando uma tela exibir dados relacionados que não devem ser alterados pelo formulário atual.

## Validação e verificação

- Após editar arquivos PHP, execute `php -l` nos arquivos PHP alterados.
- Para mudanças em JavaScript, valide o comportamento da tela afetada no navegador quando possível.
- Atualize versões de cache (`?v={numero}`) ao alterar JS ou CSS específico de view.
- Prefira verificações manuais focadas no fluxo alterado em vez de testes amplos sem relação com a mudança.
