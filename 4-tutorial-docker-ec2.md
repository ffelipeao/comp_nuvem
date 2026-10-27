# Tutorial — Containers com Docker no Amazon EC2

Computação em Nuvem · Prof. Felipe

## Objetivo da aula

Executar uma aplicação com dois containers: um servidor Apache com PHP e um banco PostgreSQL. A página consulta três produtos e permite observar comunicação pela rede Docker, falhas do banco e persistência dos dados após recriar um container.

Reserve aproximadamente 2 horas. Salvo indicação explícita na opção de acesso pelo navegador do computador, os comandos `bash` deste roteiro são executados **no terminal Ubuntu da EC2**, inclusive quando o acesso é feito pelo navegador. Não cole os comandos da EC2 no PowerShell do computador.

## Parte 1 — Preparar o ambiente

Use a instância **Ubuntu Server 24.04 LTS** da [aula de EC2](3-tutorial-servidor-linux-ec2.md). Siga as Partes 1 e 2 daquele tutorial se ainda não tiver uma instância. Confirme os custos e a elegibilidade na sua conta; esta prática não garante gratuidade. Reaproveite uma única máquina e pare-a ao terminar. O volume EBS pode continuar gerando cobrança com a máquina parada.

1. Inicie a instância pelo console AWS.
2. Abra **Conectar (Connect)** → **EC2 Instance Connect** e use o usuário `ubuntu`.
3. Mantenha a configuração **Comportamento de desligamento (Shutdown behavior): Parar (Stop)** e agende a parada:

```bash
sudo shutdown -h +180
```

Refaça o agendamento a cada início da máquina. O horário é o do servidor. Não use **Encerrar (Terminate)** para finalizar uma aula em que deseja preservar os arquivos.

4. Confira sistema, memória e disco:

```bash
cat /etc/os-release
free -h
df -h /
```

O build pode demorar em uma instância com 1 GiB de RAM. Se houver falta de memória ou disco, consulte a Parte 11 antes de repetir comandos.

**Rede:** mantenha o acesso SSH configurado na aula anterior. No roteiro principal não é necessário abrir as portas 80, 8080 ou 5432 no grupo de segurança. A página será consultada pelo próprio servidor. A instância precisa de saída para a internet para baixar pacotes e imagens.

## Parte 2 — Entender o que será criado

```text
EC2 Ubuntu
  curl → 127.0.0.1:8080 → web (Apache + PHP, porta 80)
                           |
                      rede aula-rede
                           |
                         banco (PostgreSQL, porta 5432)
                           |
                      volume aula-pgdata
```

| Conceito | Exemplo nesta aula |
| --- | --- |
| Imagem | Modelo usado para criar containers: `postgres:17` e `aula-web:1.0` |
| Container | Processo isolado em execução, com nome `web` ou `banco` |
| Dockerfile | Receita para construir a imagem Apache + PHP |
| Rede | `aula-rede`; permite ao PHP encontrar o banco pelo nome `banco` |
| Volume | `aula-pgdata`; guarda os dados fora da camada gravável do container |
| Porta publicada | `127.0.0.1:8080` da EC2 encaminha para a porta 80 do container web |

Containers Linux compartilham o kernel do host. Eles não são máquinas virtuais completas. O Apache e o PostgreSQL instalados diretamente na aula anterior são serviços distintos dos processos que executaremos nos containers.

## Parte 3 — Instalar Docker Engine

Este procedimento usa o repositório oficial do Docker para Ubuntu. Se a máquina já tem Docker, verifique `sudo docker version` e a origem da instalação antes de continuar. Não misture pacotes `docker.io` da distribuição com `docker-ce` do repositório oficial; para migrar uma instalação existente, siga as instruções de remoção de pacotes conflitantes na [documentação de instalação](https://docs.docker.com/engine/install/ubuntu/).

Em uma máquina sem Docker, execute:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
```

Cadastre o repositório:

```bash
sudo tee /etc/apt/sources.list.d/docker.sources > /dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo docker version
sudo docker run --rm hello-world
```

**Resultado esperado:** mensagem `Hello from Docker!`. O parâmetro `--rm` remove o container de teste após a execução. A imagem continua no disco.

Usaremos `sudo docker` durante toda a aula. Não é necessário adicionar o usuário ao grupo `docker`, que concede privilégios equivalentes aos de administrador. O plugin Compose foi instalado junto com as ferramentas, mas a prática usa comandos individuais para mostrar cada recurso.

## Parte 4 — Criar os arquivos do projeto

```bash
mkdir -p ~/docker-ec2/web ~/docker-ec2/sql
cd ~/docker-ec2
```

Os arquivos são os mesmos de [projetos/docker-ec2](projetos/docker-ec2/README.md). Os blocos abaixo permitem realizar a aula pelo terminal no navegador, sem depender de publicar o projeto no GitHub. Copie cada bloco inteiro, incluindo a linha final `EOF`.

**4.1 Receita da imagem web**

```bash
cat > web/Dockerfile <<'EOF'
FROM php:8.4-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY index.php /var/www/html/index.php
EOF
```

`FROM` escolhe a imagem base; `RUN` instala o driver PHP para PostgreSQL; `COPY` inclui a página. A aplicação será incorporada à imagem: editar o arquivo no host exige novo build e recriação do container web.

**4.2 Página PHP**

```bash
cat > web/index.php <<'EOF'
<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$produtos = [];
$erro = null;
try {
    $host = getenv('DB_HOST') ?: 'banco';
    $pdo = new PDO(
        "pgsql:host={$host};port=5432;dbname=loja;connect_timeout=3",
        'app_leitura',
        getenv('DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $produtos = $pdo->query('SELECT id, nome, preco, estoque FROM public.produtos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(503);
    $erro = 'Não foi possível consultar o banco de dados. Consulte os logs do container web.';
    error_log('Falha PostgreSQL: ' . $e->getMessage());
}
function h(mixed $valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Loja — Docker na EC2</title>
<style>
body{font:18px system-ui;max-width:900px;margin:40px auto;padding:0 20px;background:#f4f6fa;color:#172339}
table{border-collapse:collapse;width:100%;background:white}th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd}
.erro{padding:20px;background:#ffe1e1}code{background:#e4e8ef;padding:3px}
</style>
<h1>Produtos cadastrados</h1>
<p>Apache + PHP consultando PostgreSQL pela rede Docker.</p>
<p>Container web: <code><?= h(gethostname()) ?></code></p>
<?php if ($erro !== null): ?>
<p class="erro" role="alert"><?= h($erro) ?></p>
<?php else: ?>
<p>Consulta concluída. Total: <?= count($produtos) ?> produto(s).</p>
<table>
<thead><tr><th>ID</th><th>Produto</th><th>Preço</th><th>Estoque</th></tr></thead>
<tbody>
<?php foreach ($produtos as $produto): ?>
<tr><td><?= h($produto['id']) ?></td><td><?= h($produto['nome']) ?></td>
<td>R$ <?= h(number_format((float) $produto['preco'], 2, ',', '.')) ?></td>
<td><?= h($produto['estoque']) ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<p><a href="/">Consultar novamente</a></p>
</html>
EOF
```

O PHP usa `DB_HOST=banco` e o usuário `app_leitura`. Dentro do container web, `localhost` seria o próprio container web. A senha vem de uma variável de ambiente. Se a conexão falhar, a página retorna HTTP **503** e registra detalhes nos logs.

**4.3 Script SQL**

```bash
cat > sql/01-dados.sql <<'EOF'
\set ON_ERROR_STOP on
-- Executar conectado ao banco loja como postgres.
BEGIN;
SELECT 'CREATE ROLE app_leitura LOGIN'
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'app_leitura')
\gexec
ALTER ROLE app_leitura PASSWORD 'AulaApp2026_trocar';
CREATE TABLE IF NOT EXISTS public.produtos (
    id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    nome varchar(100) NOT NULL,
    preco numeric(10,2) NOT NULL CHECK (preco >= 0),
    estoque integer NOT NULL CHECK (estoque >= 0)
);
INSERT INTO public.produtos (id, nome, preco, estoque) VALUES
    (1, 'Teclado USB', 89.90, 15),
    (2, 'Mouse sem fio', 59.90, 25),
    (3, 'Monitor 24 polegadas', 799.00, 8)
ON CONFLICT (id) DO UPDATE SET
    nome = EXCLUDED.nome, preco = EXCLUDED.preco, estoque = EXCLUDED.estoque;
SELECT setval(pg_get_serial_sequence('public.produtos', 'id'), (SELECT max(id) FROM public.produtos));
GRANT CONNECT ON DATABASE loja TO app_leitura;
GRANT USAGE ON SCHEMA public TO app_leitura;
GRANT SELECT ON TABLE public.produtos TO app_leitura;
COMMIT;
SELECT * FROM public.produtos ORDER BY id;
EOF
```

Esse script deve ser executado com `psql`: comandos como `\gexec` pertencem a esse cliente. Ele cria um usuário de leitura e três produtos. Se executado novamente, atualiza os três produtos iniciais; portanto, não o execute novamente durante o teste de persistência.

**4.4 Arquivos de configuração**

As senhas abaixo são exemplos **exclusivamente didáticos**, consistentes com o SQL. Não reutilize senhas pessoais. Para trocar a senha da aplicação, altere também o `ALTER ROLE` no SQL e execute-o no banco.

```bash
umask 077
cat > banco.env <<'EOF'
POSTGRES_DB=loja
POSTGRES_USER=postgres
POSTGRES_PASSWORD=AulaAdmin2026_trocar
EOF
cat > web.env <<'EOF'
DB_HOST=banco
DB_PASSWORD=AulaApp2026_trocar
EOF
chmod 600 banco.env web.env
printf 'banco.env\nweb.env\n' > .gitignore
```

Não envie arquivos `.env` como evidência. Variáveis de ambiente não são um cofre: administradores do Docker podem inspecioná-las.

## Parte 5 — Criar a rede, o volume e o banco

Execute uma vez em um ambiente novo:

```bash
sudo docker network create aula-rede
sudo docker volume create aula-pgdata
sudo docker run -d --name banco \
  --network aula-rede \
  --env-file banco.env \
  --mount source=aula-pgdata,target=/var/lib/postgresql/data \
  --health-cmd='pg_isready -U postgres -d loja' \
  --health-interval=5s --health-timeout=3s --health-retries=12 \
  --restart unless-stopped \
  postgres:17
```

Usamos explicitamente a versão principal **17**. O caminho de volume deste roteiro corresponde a essa versão; a imagem PostgreSQL 18 mudou a organização dos diretórios. Consulte a [documentação da imagem oficial](https://hub.docker.com/_/postgres) antes de trocar a versão. Não use `latest` nesta prática.

Não há `-p` no banco: a porta 5432 não foi publicada no host. O PHP acessará o banco pela rede Docker.

```bash
sudo docker ps
sudo docker logs --tail 30 banco
sudo docker inspect --format '{{.State.Health.Status}}' banco
```

Aguarde o estado `healthy`, repetindo a última consulta após alguns segundos. Isso indica que o servidor aceita conexões, mas ainda precisamos criar a tabela e os usuários da aplicação.

```bash
sudo docker exec -i banco psql -U postgres -d loja < sql/01-dados.sql
```

**Resultado esperado:** tabela com Teclado USB, Mouse sem fio e Monitor 24 polegadas. O `-i` mantém a entrada aberta para receber o arquivo; não use `-t` nessa importação. O usuário `postgres` é usado apenas para administração e carga dos dados.

## Parte 6 — Construir e executar a aplicação

Ainda em `~/docker-ec2`:

```bash
sudo docker build -t aula-web:1.0 ./web
sudo docker run -d --name web \
  --network aula-rede \
  --env-file web.env \
  -p 127.0.0.1:8080:80 \
  --restart unless-stopped \
  aula-web:1.0
sudo docker ps
curl -i http://127.0.0.1:8080/
```

**Resultado esperado:** `HTTP/1.1 200 OK` e HTML com três produtos. Caso o Apache ainda esteja iniciando, aguarde alguns segundos e repita o `curl`.

O mapeamento usa somente o endereço local da EC2. O Apache instalado no host pode continuar usando a porta 80; o container usa a porta 8080 do host. A [documentação de publicação de portas](https://docs.docker.com/engine/network/port-publishing/) explica a diferença entre publicar em `127.0.0.1` e em todas as interfaces.

**Opcional: visualizar a página no navegador do computador**

Se você já tem a chave `.pem` da aula de EC2, pode usá-la no comando de túnel abaixo. Se não tem essa chave, crie um novo par no computador e autorize a chave pública na instância existente, seguindo estes passos.

**1. Criar as chaves no computador**

No **PowerShell do seu computador (Windows)**, execute:

```powershell
New-Item -ItemType Directory -Force "$HOME/.ssh" | Out-Null
ssh-keygen -t ed25519 -C "docker-ec2" -f "$HOME/.ssh/aula-docker-ec2"
Get-Content "$HOME/.ssh/aula-docker-ec2.pub"
```

Quando solicitado, defina uma frase secreta para proteger a chave privada e confirme-a. Se o arquivo já existir, não o sobrescreva: use outro nome e ajuste os comandos seguintes. Se `ssh-keygen` não for reconhecido, instale o recurso **Cliente OpenSSH** nas configurações de recursos opcionais do Windows.

No **terminal do computador com macOS ou Linux**, use:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
ssh-keygen -t ed25519 -C "docker-ec2" -f ~/.ssh/aula-docker-ec2
chmod 600 ~/.ssh/aula-docker-ec2
cat ~/.ssh/aula-docker-ec2.pub
```

O arquivo `aula-docker-ec2` é a **chave privada**: mantenha-o no computador e não o compartilhe. O arquivo `aula-docker-ec2.pub` é a **chave pública**. Copie a linha inteira exibida, começando por `ssh-ed25519`. A chave criada por esse procedimento não precisa ter extensão `.pem`.

**2. Autorizar a chave pública na EC2**

Abra **Conectar → EC2 Instance Connect** no console AWS, com o usuário `ubuntu`. No **terminal Ubuntu da EC2**, execute:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
cat >> ~/.ssh/authorized_keys <<'EOF'
COLE_AQUI_A_LINHA_COMPLETA_DA_CHAVE_PUBLICA
EOF
chmod 600 ~/.ssh/authorized_keys
```

Antes de executar, substitua `COLE_AQUI_A_LINHA_COMPLETA_DA_CHAVE_PUBLICA` pela linha copiada do arquivo `.pub`, sem quebrá-la em várias linhas. O operador `>>` acrescenta a chave e preserva os acessos existentes. Mantenha a sessão do navegador aberta até testar o túnel. Apenas criar ou importar um par no console AWS não instala a chave em uma instância já existente; é necessário autorizá-la no usuário, conforme a [documentação da AWS sobre adicionar chaves](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/replacing-key-pair.html).

**3. Abrir o túnel no computador**

No grupo de segurança da instância, permita **SSH / TCP / porta 22** com origem **Meu IP (My IP)**, conforme a aula de EC2. Confira o IPv4 público atual da instância. Não é necessário abrir a porta 8080 no grupo de segurança.

No **PowerShell do computador**, para a chave recém-criada, substitua `IP_PUBLICO_EC2` e execute:

```powershell
ssh -i "$HOME/.ssh/aula-docker-ec2" -o ExitOnForwardFailure=yes -N -L 8080:127.0.0.1:8080 ubuntu@IP_PUBLICO_EC2
```

No **terminal do computador com macOS ou Linux**, o equivalente é:

```bash
ssh -i ~/.ssh/aula-docker-ec2 -o ExitOnForwardFailure=yes -N -L 8080:127.0.0.1:8080 ubuntu@IP_PUBLICO_EC2
```

Se usar a chave `.pem` anterior, informe o caminho dela no lugar da chave nova. Exemplo no PowerShell:

```powershell
ssh -i "C:\caminho\chave-seunome.pem" -N -L 8080:127.0.0.1:8080 ubuntu@IP_PUBLICO_EC2
```

Na primeira conexão, confira a impressão digital da chave do servidor antes de aceitar; se definiu uma frase secreta para sua chave, informe-a quando solicitada. Com `-N`, o terminal fica ocupado sem abrir um prompt remoto: isso é esperado. Mantenha esse terminal aberto e acesse **http://localhost:8080** no navegador local. Se a porta local estiver ocupada, troque apenas o primeiro `8080` por `8081` e abra `http://localhost:8081`. O terminal EC2 Instance Connect no navegador não cria esse túnel para o computador. Para encerrar o túnel, use `Ctrl+C` no terminal local.

## Parte 7 — Inspecionar e comprovar o acesso de leitura

Na EC2:

```bash
sudo docker image ls
sudo docker network inspect aula-rede
sudo docker volume inspect aula-pgdata
sudo docker logs --tail 20 web
sudo docker exec web php -m
```

Localize `pdo_pgsql` na lista de módulos. A rede deve conter os dois containers; o volume deve existir independentemente deles.

Teste a permissão do usuário usado pelo PHP. O comando solicita a senha `AulaApp2026_trocar` sem exibi-la:

```bash
sudo docker exec -it banco psql -h 127.0.0.1 -U app_leitura -d loja -W
```

No prompt `loja=>`, execute:

```sql
SELECT * FROM public.produtos ORDER BY id;
UPDATE public.produtos SET estoque = 0 WHERE id = 1;
\q
```

**Resultado esperado:** o `SELECT` funciona; o `UPDATE` retorna `permission denied for table produtos`. Isso demonstra que a aplicação pode consultar os dados, mas não alterá-los.

## Parte 8 — Simular uma falha do banco

```bash
sudo docker stop banco
curl -i http://127.0.0.1:8080/
sudo docker logs --tail 20 web
```

**Resultado esperado:** HTTP **503**, mensagem amigável no HTML e erro de conexão nos logs. O container web continua executando: um processo ativo não garante que todas as dependências estejam disponíveis.

Recupere o serviço:

```bash
sudo docker start banco
sudo docker inspect --format '{{.State.Health.Status}}' banco
```

Aguarde `healthy`, depois repita:

```bash
curl -i http://127.0.0.1:8080/
```

A resposta deve voltar a HTTP **200** sem recriar o container web.

## Parte 9 — Comprovar a persistência

Altere um dado usando o administrador e confira na página:

```bash
sudo docker exec banco psql -U postgres -d loja -c "UPDATE public.produtos SET estoque = 99 WHERE id = 1;"
curl -s http://127.0.0.1:8080/
```

Agora remova **somente o container do banco** e crie outro usando o mesmo volume:

```bash
sudo docker stop banco
sudo docker rm banco
sudo docker run -d --name banco \
  --network aula-rede \
  --env-file banco.env \
  --mount source=aula-pgdata,target=/var/lib/postgresql/data \
  --health-cmd='pg_isready -U postgres -d loja' \
  --health-interval=5s --health-timeout=3s --health-retries=12 \
  --restart unless-stopped \
  postgres:17
sudo docker inspect --format '{{.State.Health.Status}}' banco
```

Aguarde `healthy`. **Não execute novamente o script SQL.**

```bash
sudo docker exec banco psql -U postgres -d loja -c "SELECT id, nome, estoque FROM public.produtos ORDER BY id;"
curl -i http://127.0.0.1:8080/
```

**Resultado esperado:** estoque do produto 1 permanece **99** e a página retorna 200. O novo container usa o nome `banco`, que o PHP resolve novamente pela rede. Se uma consulta ocorrer durante a inicialização, repita-a após a prontidão do banco.

As variáveis `POSTGRES_DB`, `POSTGRES_USER` e `POSTGRES_PASSWORD` inicializam um diretório de dados vazio. Alterar `banco.env` não troca a senha de um banco já existente no volume; para isso, use `ALTER ROLE`. O volume preserva os dados ao remover containers, mas não substitui backup e fica no disco da própria EC2.

### Prática complementar — Executar SQL pelo psql no container

Realize esta prática com o container `banco` em execução. Usaremos uma tabela separada, `public.produtos_pratica`, para manter os três produtos da página e o estoque do teste de persistência.

**1. Acessar o banco como administrador**

No **terminal Ubuntu da EC2**, execute:

```bash
sudo docker exec -it banco psql -U postgres -d loja
```

Esse comando abre diretamente o cliente `psql` dentro do container `banco`, conectado ao banco `loja` como usuário `postgres`. O prompt deve mudar para `loja=#`. A partir daí, digite os comandos SQL abaixo **dentro do psql**, terminando cada instrução com `;`. Não digite `sudo docker` nesse prompt.

Comandos úteis do próprio `psql` (não precisam de `;`):

```text
\conninfo
\du
\dt public.*
```

Eles mostram, respectivamente, a conexão atual, os usuários e as tabelas. Neste container da aula, a conexão local usada pelo administrador pode dispensar senha; isso não significa que o usuário da aplicação tenha acesso administrativo.

**2. Criar um usuário e definir sua senha**

Execute uma vez:

```sql
CREATE USER aluno_sql;
```

Defina a senha pelo comando do `psql`, que solicita a digitação e a confirmação sem exibi-la:

```text
\password aluno_sql
```

Use uma senha exclusivamente didática e guarde-a para o teste de conexão. `CREATE USER` cria um papel com permissão de login; não concede privilégios de administrador. Se o usuário já existir, não repita o `CREATE USER`; use `\password aluno_sql` para definir outra senha, se necessário.

**3. Criar uma tabela**

Ainda como `postgres`, execute:

```sql
CREATE TABLE public.produtos_pratica (
    id integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nome varchar(100) NOT NULL,
    preco numeric(10,2) NOT NULL CHECK (preco >= 0),
    estoque integer NOT NULL CHECK (estoque >= 0)
);
```

Confira a estrutura:

```text
\d public.produtos_pratica
```

Crie a tabela apenas uma vez. Se ela já existir de uma execução anterior, confira sua estrutura e os dados antes de continuar.

**4. Inserir e consultar dados**

```sql
INSERT INTO public.produtos_pratica (nome, preco, estoque)
VALUES
    ('Caderno', 25.90, 10),
    ('Caneta', 3.50, 30),
    ('Mochila', 120.00, 5)
RETURNING *;

SELECT * FROM public.produtos_pratica ORDER BY id;
```

O banco gera os IDs automaticamente. `RETURNING` mostra as linhas afetadas pelo comando. Repetir esse `INSERT` cria novos registros.

**5. Atualizar dados**

Consulte os IDs e substitua `1` pelo ID do registro que deseja alterar:

```sql
UPDATE public.produtos_pratica
SET preco = 27.90, estoque = 15
WHERE id = 1
RETURNING *;

SELECT * FROM public.produtos_pratica ORDER BY id;
```

**6. Excluir dados**

Consulte os IDs e substitua `2` pelo ID do registro que deseja excluir:

```sql
DELETE FROM public.produtos_pratica
WHERE id = 2
RETURNING *;

SELECT * FROM public.produtos_pratica ORDER BY id;
```

**Atenção:** `UPDATE` e `DELETE` sem `WHERE` afetam todas as linhas da tabela. Por padrão, o `psql` confirma cada comando bem-sucedido automaticamente. Para experimentar uma exclusão e desfazê-la, use uma transação:

```sql
BEGIN;
DELETE FROM public.produtos_pratica WHERE id = 1 RETURNING *;
ROLLBACK;
SELECT * FROM public.produtos_pratica ORDER BY id;
```

`ROLLBACK` desfaz a exclusão dessa transação. Use `COMMIT` no lugar dele somente quando quiser confirmar a alteração.

**7. Conceder permissões ao novo usuário**

Ainda como `postgres`, permita que `aluno_sql` consulte e altere apenas a tabela da prática, incluindo o uso da sequência que gera os IDs:

```sql
GRANT CONNECT ON DATABASE loja TO aluno_sql;
GRANT USAGE ON SCHEMA public TO aluno_sql;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.produtos_pratica TO aluno_sql;
GRANT USAGE ON SEQUENCE public.produtos_pratica_id_seq TO aluno_sql;
```

Saia do `psql`:

```text
\q
```

No **terminal Ubuntu da EC2**, conecte como o novo usuário. O parâmetro `-h 127.0.0.1` usa TCP dentro do container, e `-W` solicita a senha definida anteriormente:

```bash
sudo docker exec -it banco psql -h 127.0.0.1 -U aluno_sql -d loja -W
```

No prompt `loja=>`, teste:

```sql
SELECT current_user;
INSERT INTO public.produtos_pratica (nome, preco, estoque)
VALUES ('Borracha', 2.00, 20)
RETURNING *;
SELECT * FROM public.produtos_pratica ORDER BY id;
```

Você também pode repetir os exemplos de `UPDATE` e `DELETE` com os IDs desejados nessa tabela. As permissões de `app_leitura` permanecem apenas de consulta na tabela `public.produtos`, como demonstrado na Parte 7. Finalize com `\q` para voltar ao terminal da EC2.

## Parte 10 — Atividade para entrega

Entregue um documento com as seguintes evidências, sem senhas ou arquivos `.env`:

1. Saída de `sudo docker ps`, mostrando `web` e `banco`.
2. Página com os três produtos, pelo navegador com túnel ou pelo HTML retornado por `curl`.
3. Resposta HTTP 503 com o banco parado e resposta 200 após recuperá-lo.
4. Consulta com estoque 99 após remover e recriar o container do banco.
5. Explicação, com suas palavras: diferença entre imagem e container; função da rede e do volume; motivo para usar `banco` em vez de `localhost` no PHP.
6. Confirmação no console de que a EC2 ficou **Parada (Stopped)** ao finalizar.

## Parte 11 — Resolver problemas

| Sintoma | Verificação e correção |
| --- | --- |
| `permission denied` ao chamar Docker | Use `sudo docker`, como nos exemplos. |
| `Cannot connect to the Docker daemon` | Execute `sudo systemctl status docker` e `sudo systemctl start docker`. |
| Nome `banco` ou `web` já em uso | Consulte `sudo docker ps -a`. Para retomar um container existente, use `sudo docker start banco web`. Remova e recrie somente se essa for sua intenção. |
| Rede ou volume já existe | Inspecione o recurso e reutilize-o se for desta aula; não precisa criá-lo novamente. |
| `curl` recebe conexão recusada | Confira `sudo docker ps -a`, os logs de `web` e o mapeamento em `sudo docker port web`. |
| Página retorna 503 | Confirme banco `healthy`, SQL importado, ambos na mesma rede e senha de `web.env` igual à do usuário `app_leitura`. Consulte os logs de `web`. |
| `could not find driver` | Confira `pdo_pgsql` com `sudo docker exec web php -m`. Reconstrua a imagem e recrie `web` se tiver usado outro Dockerfile. |
| Senha continua antiga após editar `.env` | Um container existente mantém o ambiente de sua criação. Recrie `web` para aplicar o arquivo; a senha armazenada no PostgreSQL exige `ALTER ROLE`. |
| Porta 8080 ocupada | Verifique `sudo ss -ltnp`. Ao recriar `web`, use `-p 127.0.0.1:8081:80` e ajuste `curl` e túnel. |
| Build termina com `Killed` ou erro de espaço | Consulte `free -h`, `df -h /`, `sudo docker system df` e `sudo journalctl -k -n 50`. Peça orientação ao professor antes de aumentar recursos pagos ou apagar dados. |
| Falha ao baixar imagens | Verifique saída para a internet, DNS e mensagens de limite de downloads do Docker Hub. Aguarde ou autentique-se com sua conta, se necessário. |
| Navegador não abre a página | No roteiro, o IP público da EC2 não serve a página. Use `curl` na EC2 ou mantenha o túnel SSH local ativo. |

Para aplicar alterações na página, faça novo build e recrie apenas o serviço web:

```bash
cd ~/docker-ec2
sudo docker build -t aula-web:1.0 ./web
sudo docker stop web
sudo docker rm web
sudo docker run -d --name web \
  --network aula-rede --env-file web.env \
  -p 127.0.0.1:8080:80 --restart unless-stopped aula-web:1.0
```

## Parte 12 — Encerrar a aula

**Para continuar em outra aula:**

```bash
sudo docker stop web banco
```

No console AWS, selecione a instância → **Estado da instância (Instance state)** → **Parar instância (Stop instance)**. Aguarde **Parada (Stopped)**. Fechar o navegador não para a EC2.

Na próxima aula, inicie a EC2, reconecte, agende novamente `sudo shutdown -h +180` e execute `sudo docker start banco web`. Aguarde o banco ficar saudável antes de testar. Containers parados manualmente não devem ser considerados automaticamente iniciados pela política `unless-stopped`. O IP público pode mudar; atualize o túnel.

**Opcional, somente quando não precisar mais do projeto:** os comandos abaixo removem os recursos desta aula. O comando de volume apaga definitivamente os dados PostgreSQL.

```bash
sudo docker stop web banco
sudo docker rm web banco
sudo docker network rm aula-rede
sudo docker image rm aula-web:1.0
# Execute apenas se quiser apagar todos os dados da loja:
sudo docker volume rm aula-pgdata
```

Pare a EC2 pelo console mesmo se optar pela limpeza. Não execute limpezas globais como `docker system prune --volumes` em uma máquina compartilhada.
