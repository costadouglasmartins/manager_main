Manager System

- Apenas para usuários root;
- Este é a administração para todos os outros sistemas.

## Deploy

See [docs/deployment.md](docs/deployment.md) for the GitHub Actions workflow,
repository secrets, production prerequisites, and deployment verification.

## Dependências compartilhadas

As dependências PHP deste sistema são centralizadas no diretório `manager_assets`, localizado no mesmo nível de `manager_main` e `manager`. Instale-as uma única vez:

```sh
cd ../manager_assets
composer install --no-dev --optimize-autoloader
```

O caminho pode ser substituído pela variável de ambiente `SHARED_ASSETS_PATH`. O `composer.json`, o `composer.lock` e o diretório `vendor` não são mantidos dentro deste projeto.

## Configuração do banco de dados e do chat

As credenciais do banco de dados e a chave compartilhada do chat ficam centralizadas em:

```text
app/Core/Config.php
```

Edite nesse arquivo as constantes `DB_HOST`, `DB_PORT`, `DB_DATABASE_MANAGER`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`, `SUPPORT_CHAT_SECRET`, `SUPPORT_CHAT_WS_HOST` e `SUPPORT_CHAT_WS_PORT`. Mantenha os mesmos dados no arquivo correspondente do `manager`.

# Regras de programação do projeto

Este documento registra as convenções de desenvolvimento que devem ser seguidas neste projeto.

## Idioma e nomenclatura

- Mantenha comentários de métodos e documentação em inglês.
- Escreva nomes de métodos em inglês.
- Use `camelCase` em nomes de métodos quando a convenção do PHP/framework ao redor pedir esse padrão.
- Prefira nomes claros para models, controllers e services, sempre alinhados com a entidade de domínio.
- Crie novos arquivos, classes, métodos, funções e variáveis com nomes em inglês.
- Em comentários e docblocks, descreva o tipo do dado, o nome do parâmetro e quando houver referência, mantendo a documentação objetiva em inglês.
- Em novos códigos, documente métodos com comentários de bloco iniciados por `/**`, mantenha cada linha intermediária iniciada por `*` e finalize o bloco com `**/`.

Exemplo recomendado para comentários de métodos:

```php
/**
 * Describe what the method does.
 * @param int $id Identifier used by the method.
 * @return bool True when the operation succeeds.
 **/
```

- Separe novos métodos e funções com o bloco de comentário padrão do projeto, mantendo uma seção clara antes de cada grupo de responsabilidades.
- Ao criar variáveis, adicione um comentário explicativo imediatamente acima da declaração, sempre em inglês.

## Estrutura PHP

- Mantenha actions de controllers enxutas: valide permissões, leia entradas, chame models/services e escolha a resposta.
- Coloque escritas no banco e fluxos transacionais em models ou services.
- Reutilize helpers existentes em `app/Helpers` antes de criar novas lógicas de formatação ou parsing.
- Use `requirePermission()` ou `requireAnyPermission()` em actions que expõem dados protegidos.
- Retorne JSON com `$this->json()` em endpoints AJAX.
- Evite refatorações sem relação direta com a alteração solicitada.

## Código reutilizável

- Sempre que um código puder ser reutilizado por mais de uma aplicação não o mantenha duplicado ou embutido em uma view.
- Antes de criar uma nova biblioteca, verifique se já existe uma biblioteca em `manager_assets/` que possa ser estendida.
- Quando for necessária uma nova biblioteca JavaScript reutilizável, crie-a no diretório compartilhado seguindo o padrão `manager_assets/<nome_da_lib>/<nome_da_lib>.js`.
- Mantenha a biblioteca independente dos detalhes de uma tela específica e exponha somente as opções necessárias para que cada tela configure seu comportamento.
- Nas views de `manager` e `manager_main`, apenas carregue e inicialize a biblioteca compartilhada; mantenha nelas somente o código que for realmente exclusivo daquela tela.

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
- Em HTML, mantenha cada elemento em uma única linha e separe os atributos por espaços, sem quebrar cada atributo em linhas separadas.

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
- Ao criar tabelas no banco de dados, use nomes sempre em inglês: quando houver uma única palavra, escreva-a no plural; quando houver múltiplas palavras, mantenha a primeira no singular e separe as seguintes por underline, sempre no plural, como `financial_operations_payments`.
- Envolva fluxos de create/update/delete em múltiplas tabelas com transações.
- Faça rollback da transação quando qualquer escrita falhar.
- Mantenha relacionamentos de tenants explícitos por meio de `tenant_users`.
- Ao atualizar registros filhos repetíveis, valide tanto o `id` quanto a chave estrangeira do dono quando possível, por exemplo `id` + `person_id`.
- Normalize documentos, datas e telefones com helpers existentes antes de persistir.

## Módulos Tenant Aware e rotinas de exclusão

- Antes de concluir qualquer CRUD ou armazenamento persistente, classifique os dados como globais ou Tenant Aware.
- Todo CRUD Tenant Aware deve incluir, na mesma mudança, cada associação direta ou indireta ao tenant no manifesto e na execução de `app/Services/TenantDeletionService.php`. Inclua registros no banco central, tabelas nas bases `manager_NNNN`, arquivos persistentes, temporários e caches.
- Quando o módulo criar tabelas na base dedicada do tenant, registre-as explicitamente em `TenantProvisioner::DATABASE_MODULES`, em `app/Services/TenantProvisioner.php`, para que todos os registros apareçam no manifesto de confirmação da exclusão. O fallback de descoberta é somente uma rede de segurança e não substitui esse catálogo.
- Quando o CRUD referenciar `users` ou `people`, revise e atualize também `app/Services/UserDeletionService.php`, preservando os bloqueios de pessoas, usuários e referências compartilhadas ou protegidas.
- O manifesto deve informar a base, o módulo, a tabela, o identificador e a descrição de cada registro que será removido. A exclusão deve usar exatamente o mesmo plano apresentado ao usuário, recalculado no servidor no momento da confirmação.
- Registre tabelas filhas antes das tabelas pai e mantenha a ordem de exclusão compatível com as chaves estrangeiras, incluindo dependências em `manager_main` e nas bases `manager_NNNN`.
- Ao adicionar uma referência a `tenants`, revise `TenantDeletionService`; ao referenciar `users` ou `people`, revise também `UserDeletionService`, mesmo quando a chave estrangeira usar `CASCADE`, `SET NULL` ou quando bancos antigos ainda não possuírem a constraint.
- Nunca burle o gate de metadados em runtime para aceitar uma associação ainda não registrada; catalogue a associação detectada.
- A exclusão de um tenant pode remover sua base dedicada somente após a confirmação integral. A exclusão de um usuário nunca deve excluir uma base de dados; deve remover o usuário, sua pessoa associada, seus contatos e apenas os demais registros listados no manifesto. Pessoas protegidas ou compartilhadas com outro usuário devem bloquear esse fluxo.
- Preserve as proteções de registros especiais e os catálogos globais definidos pelos services. Inclua testes de manifesto, proteção e rollback para cada novo módulo Tenant Aware.
- Um CRUD Tenant Aware não está concluído sem testes aplicáveis de manifesto, remoção e rollback, incluindo os armazenamentos não relacionais que ele criar.

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
