# Projeto da aula

Siga o [tutorial completo](../../4-tutorial-docker-ec2.md).

- `web/Dockerfile`: imagem Apache + PHP com driver PostgreSQL.
- `web/index.php`: listagem e resposta HTTP 503 em falhas do banco.
- `sql/01-dados.sql`: estrutura, usuário de leitura e três produtos; execute via psql.

As senhas do roteiro são exclusivamente didáticas. Os arquivos `banco.env` e `web.env` são criados na EC2 durante a aula e ignorados pelo Git.
