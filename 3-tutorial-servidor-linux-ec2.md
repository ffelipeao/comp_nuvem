# Tutorial — Servidor Linux no Amazon EC2

Computação em Nuvem · 29/09/2026 · Prof. Felipe

> **Idioma da console:** os nomes de telas, campos e botões aparecem primeiro em português e, entre parênteses, em inglês. Exemplo: **Executar instâncias (Launch instances)**.

## Objetivo da aula

Nesta aula você vai criar um servidor Linux (Ubuntu) na AWS com a menor configuração possível, acessá-lo pelo **terminal no navegador do console AWS**, praticar comandos básicos e instalar Apache e PostgreSQL. Os serviços Apache e PostgreSQL não ficam expostos na internet, e a máquina é desligada no final. O SSH pelo terminal do seu computador fica como opção.

**Pré-requisitos**

- Conta AWS no **plano gratuito**, com MFA e **Orçamento de gasto zero (Zero spend budget)** ativos (veja o tutorial de S3, Parte 3).
- Região **us-east-1 (Norte da Virgínia)**, a mesma das aulas anteriores.
- Navegador com acesso ao console AWS; não é necessário configurar SSH no computador para a prática principal.
- **Opcional, para SSH local e túnel:** **PowerShell**, **Terminal** (Mac/Linux) ou **Git Bash** no seu computador.

**As 5 regras de custo zero desta aula**

| Regra | Por quê |
| --- | --- |
| Use **apenas** tipo de instância com o selo **Qualificado para o nível gratuito (Free tier eligible)** (recomendado: `t3.micro`) | Tipos maiores consomem os créditos muito mais rápido |
| Disco de **8 GiB gp3** (o padrão) e **uma** instância por aluno | O gratuito cobre até 30 GiB de EBS por mês somando todos os discos |
| **Não** crie Elastic IP, Load Balancer, NAT Gateway nem RDS nesta aula | São cobrados por hora, mesmo parados |
| Libere no firewall **só a porta 22 para o EC2 Instance Connect da região**; para SSH local, adicione **Meu IP** | Permite o terminal pelo console e mantém Apache e PostgreSQL sem acesso externo |
| **Pare (Stop) a instância ao terminar** | Instância ligada consome horas e o IP público é cobrado por hora (US$ 0,005/h) enquanto ela roda |

> No plano gratuito, o uso de EC2 é descontado dos seus créditos (US$ 100 iniciais). Uma `t3.micro` ligada só durante as aulas consome centavos; esquecida ligada o mês inteiro, consome vários dólares. **Ligou, usou, desligou.**

## Parte 1 — Criar a instância EC2

Você vai lançar uma máquina Ubuntu `t3.micro` com 8 GiB de disco e preparar o acesso pelo terminal do console AWS.

1. No console, confirme a região **us-east-1** no canto superior direito.
2. Pesquise **EC2** na barra do topo → **Instâncias (Instances)** → **Executar instâncias (Launch instances)**.
3. Preencha a página conforme a tabela:

| Campo | Valor | Observação |
| --- | --- | --- |
| **Nome (Name)** | `linux-seunome` | Ex.: `linux-maria` |
| **Imagens de aplicação e sistema operacional — AMI (Application and OS Images)** | **Ubuntu Server 24.04 LTS**, arquitetura **64 bits (x86)** | Deve exibir **Qualificado para o nível gratuito (Free tier eligible)** |
| **Tipo de instância (Instance type)** | **t3.micro** | 2 vCPU, 1 GiB de RAM; confira o selo **Qualificado para o nível gratuito (Free tier eligible)** |
| **Par de chaves — login (Key pair — login)** | **Criar novo par de chaves (Create new key pair)** → nome `chave-seunome`, tipo **ED25519**, formato **.pem** | Guarde para o SSH local opcional; não é preciso configurar nem usar esse arquivo no acesso pelo console |
| **Configurações de rede (Network settings)** → **Editar (Edit)** | VPC padrão; **Atribuir IP público automaticamente: Habilitar (Auto-assign public IP: Enable)** | Necessário para o SSH |
| **Firewall — grupos de segurança (Firewall — security groups)** | **Criar grupo de segurança (Create security group)** → nome `sg-linux-seunome` | |
| Regra de entrada | **SSH, porta 22, tipo de origem: Meu IP (Source type: My IP)** | **Não** use `0.0.0.0/0`, isto é, **Qualquer lugar (Anywhere)** |
| Outras regras (HTTP/HTTPS) | **Deixe desmarcadas** | O Apache não ficará exposto |
| **Configurar armazenamento (Configure storage)** | **8 GiB, gp3** | Não aumente |
| **Detalhes avançados (Advanced details)** | Confirme **Comportamento de desligamento: Parar (Shutdown behavior: Stop)** | Necessário para o desligamento agendado parar a instância sem encerrá-la |

4. Confira o painel **Resumo (Summary)** à direita: **Número de instâncias: 1 (Number of instances: 1)**.
5. Clique em **Executar instância (Launch instance)** → **Visualizar todas as instâncias (View all instances)**.
6. Aguarde **Estado da instância: Em execução (Instance state: Running)** e **Verificações de status: 3/3 verificações aprovadas (Status check: 3/3 checks passed)** (1 a 3 minutos).
7. Selecione a instância e abra a aba **Segurança (Security)** → clique no grupo `sg-linux-seunome` → **Editar regras de entrada (Edit inbound rules)** → **Adicionar regra (Add rule)**.
8. Escolha **SSH**, porta **22**. Em **Origem (Source)**, escolha **Personalizado (Custom)** e pesquise a lista de prefixos IPv4 `com.amazonaws.us-east-1.ec2-instance-connect`. Selecione essa lista gerenciada pela AWS e salve as regras. Se usar outra região, substitua `us-east-1` pelo código dela. **Não** use `0.0.0.0/0`.
9. A regra **Meu IP** criada no lançamento serve apenas para o SSH local opcional. Se for usar somente o navegador, remova essa regra e mantenha a regra do EC2 Instance Connect.
10. Volte a **Instâncias (Instances)** e siga imediatamente a Parte 2: abra o terminal e agende o desligamento antes de continuar a prática.

> O terminal do console recebe conexões do serviço EC2 Instance Connect da AWS, não do IP do seu computador. Por isso, a regra **Meu IP** sozinha não permite esse acesso. A instância também precisa de IP público, rota para a internet e permissões IAM para EC2 Instance Connect. A AMI oficial Ubuntu Server 24.04 LTS já inclui o suporte necessário. Veja os [pré-requisitos do EC2 Instance Connect](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-instance-connect-prerequisites.html).

> **A chave `.pem` é a senha do seu servidor.** Não envie no grupo da turma, não coloque no GitHub e não salve em pasta compartilhada. Se perder, não há como baixar de novo.

**Mudou de rede (casa → faculdade)?** No terminal pelo console, a origem continua sendo o EC2 Instance Connect. Para o **SSH local opcional**, atualize a regra **Meu IP (My IP)** em **Grupos de segurança (Security Groups)** → `sg-linux-seunome` → **Editar regras de entrada (Edit inbound rules)**.

## Parte 2 — Acessar pelo terminal do console AWS

**2.1 Abrir o terminal no navegador (acesso principal)**

1. No console AWS, abra **EC2** → **Instâncias (Instances)**.
2. Selecione `linux-seunome`, com estado **Em execução (Running)**.
3. Clique em **Conectar (Connect)** → aba **EC2 Instance Connect**.
4. Escolha a conexão pelo **IP público (Public IP)** e confira o usuário **`ubuntu`**.
5. Clique em **Conectar (Connect)**. Um terminal será aberto no navegador.
6. Aguarde o prompt semelhante a `ubuntu@ip-172-31-20-15:~$`. **Você está dentro do servidor.** Digite os comandos depois do `$`, sem copiar esse símbolo.

Esse acesso não exige instalar programas, mover a chave `.pem` ou configurar SSH no seu computador. Use esse terminal para os comandos das próximas partes. Se a conexão falhar, confira a regra do EC2 Instance Connect da Parte 1 e as permissões IAM da conta.

**2.2 Primeira ação no servidor: agendar o desligamento**

Logo após criar e entrar na instância, **antes de instalar ou atualizar qualquer programa**, execute:

```bash
sudo shutdown -h +180
```

Esse comando agenda o desligamento para **180 minutos (3 horas) a partir de agora**. `sudo` dá permissão administrativa, `shutdown -h` solicita o desligamento e `+180` define a espera em minutos. Confira a mensagem com a data e a hora previstas, no fuso do servidor.

O agendamento continua ativo mesmo se você fechar o terminal ou o navegador. Com **Comportamento de desligamento: Parar (Stop)**, a instância será parada e os arquivos no volume EBS serão preservados. **Confirme essa configuração na criação; não use Encerrar (Terminate)**. [Comportamento de desligamento na AWS](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/Using_ChangingInstanceInitiatedShutdownBehavior.html).

> Esta é uma rede de segurança caso você esqueça a máquina ligada. Ainda assim, pare a instância pelo console ao terminar a aula. Agende novamente no começo de cada aula ou depois de reiniciar/iniciar a máquina; o agendamento não é permanente. Volumes EBS e outros recursos podem continuar gerando custos com a instância parada.

Se precisar de mais tempo, cancele o agendamento e, em seguida, marque um novo prazo:

```bash
sudo shutdown -c
sudo shutdown -h +180
```

**2.3 Atualizar o sistema**

```bash
sudo apt update && sudo apt upgrade -y
```

Se aparecer uma tela roxa perguntando sobre serviços a reiniciar, aperte **Enter** para aceitar o padrão.

**2.4 Opção: acessar pelo terminal da sua máquina local (SSH)**

Você pode continuar toda a prática principal no navegador. Use esta opção se preferir o terminal local ou quiser experimentar o túnel da seção 4.2.

No console, copie o **Endereço IPv4 público (Public IPv4 address)** da instância. No grupo de segurança, adicione uma regra **SSH, porta 22, origem Meu IP (My IP)**, mantendo a regra do EC2 Instance Connect se quiser continuar usando o navegador.

**Preparar a chave no seu computador:** o SSH recusa chaves que outros usuários possam ler.

*Linux / Mac (Terminal):*

```bash
mkdir -p ~/.ssh
mv ~/Downloads/chave-seunome.pem ~/.ssh/
chmod 400 ~/.ssh/chave-seunome.pem
```

*Windows (PowerShell):*

```powershell
mkdir $env:USERPROFILE\.ssh -Force
Move-Item $env:USERPROFILE\Downloads\chave-seunome.pem $env:USERPROFILE\.ssh\
icacls $env:USERPROFILE\.ssh\chave-seunome.pem /inheritance:r /grant:r "$($env:USERNAME):R"
```

**Conectar pelo terminal local:**

Troque o IP pelo da sua instância:

```bash
ssh -i ~/.ssh/chave-seunome.pem ubuntu@3.85.120.44
```

(No PowerShell: `ssh -i $env:USERPROFILE\.ssh\chave-seunome.pem ubuntu@3.85.120.44`)

1. Na primeira conexão aparece `Are you sure you want to continue connecting (yes/no)?`. Digite `yes` e Enter.
2. O prompt muda para algo como `ubuntu@ip-172-31-20-15:~$`. **Você está dentro do servidor.**
3. Se esse for seu primeiro acesso após iniciar a máquina, execute `sudo shutdown -h +180`, conforme a seção 2.2.
4. Para sair da sessão, digite `exit`. Isso não para a instância.

## Parte 3 — Comandos básicos do Linux

Experimente cada comando na ordem das tabelas. Todos são seguros; os que exigem cuidado estão marcados com ⚠.

**3.1 Onde estou e quem sou**

| Comando | O que faz |
| --- | --- |
| `whoami` | Mostra o usuário logado (`ubuntu`) |
| `hostname` | Mostra o nome da máquina |
| `pwd` | Mostra a pasta atual (*print working directory*) |
| `uname -a` | Mostra a versão do kernel Linux |
| `cat /etc/os-release` | Mostra a distribuição e versão (Ubuntu 24.04) |
| `date` | Mostra data e hora do servidor (em UTC) |
| `uptime` | Há quanto tempo a máquina está ligada e a carga |

**3.2 Navegar entre pastas**

| Comando | O que faz |
| --- | --- |
| `ls` | Lista arquivos da pasta atual |
| `ls -la` | Lista tudo, inclusive ocultos (começam com `.`), com permissões e tamanhos |
| `cd /etc` | Entra na pasta `/etc` (caminho absoluto) |
| `cd ..` | Sobe um nível |
| `cd ~` ou só `cd` | Volta para a sua pasta pessoal (`/home/ubuntu`) |
| `cd -` | Volta para a pasta anterior |
| `tree -L 1 /` | Mostra as pastas principais do sistema (instale antes: `sudo apt install tree`) |

**3.3 Criar, ler e editar arquivos**

| Comando | O que faz |
| --- | --- |
| `mkdir aula` | Cria a pasta `aula` |
| `mkdir -p aula/a/b` | Cria pastas aninhadas de uma vez |
| `touch notas.txt` | Cria um arquivo vazio |
| `echo "Olá, nuvem" > notas.txt` | Escreve texto no arquivo (**substitui** o conteúdo) |
| `echo "mais uma linha" >> notas.txt` | **Acrescenta** uma linha ao final |
| `cat notas.txt` | Mostra o arquivo inteiro |
| `less /etc/services` | Lê arquivo longo página a página (`q` para sair, `/` para buscar) |
| `head -n 5 arquivo` / `tail -n 5 arquivo` | Mostra as 5 primeiras / últimas linhas |
| `wc -l arquivo` | Conta as quebras de linha do arquivo; nos exemplos abaixo, corresponde ao número de linhas |
| `nano notas.txt` | Editor de texto no terminal: **Ctrl+O** salva, **Ctrl+X** sai |
| `cp notas.txt copia.txt` | Copia um arquivo |
| `mv copia.txt aula/` | Move (ou renomeia) um arquivo |
| ⚠ `rm copia.txt` | Apaga o arquivo **sem lixeira** — não tem volta |
| ⚠ `rm -r aula` | Apaga a pasta e tudo dentro dela. Confira o caminho com `pwd` antes |

**Prática guiada: criar arquivos com texto e dados de exemplo**

Execute estes comandos **no terminal da EC2**, pelo console AWS ou por SSH. Não é necessário usar `sudo`: os arquivos ficam na sua pasta pessoal.

```bash
mkdir -p ~/aula-ec2/pratica
cd ~/aula-ec2/pratica
pwd
```

Crie um arquivo com três linhas de texto:

```bash
printf 'Estou usando Linux na EC2.\nEstou aprendendo a criar arquivos.\nVou praticar comandos no terminal.\n' > mensagem.txt
cat mensagem.txt
wc -l mensagem.txt
```

`printf` escreve o texto; cada `\n` cria uma quebra de linha. A saída de `wc -l` deve ser `3 mensagem.txt`. O símbolo `>` grava em um arquivo novo ou **substitui todo o conteúdo** de um arquivo existente.

Acrescente uma linha sem apagar as anteriores:

```bash
printf 'Esta linha foi acrescentada ao final.\n' >> mensagem.txt
cat mensagem.txt
wc -l mensagem.txt
```

Agora o resultado deve ser `4 mensagem.txt`. O símbolo `>>` acrescenta conteúdo ao final.

Para criar várias linhas de forma mais legível, use `cat` com um delimitador. Copie o bloco inteiro, incluindo a última linha `EOF`:

```bash
cat > alunos.csv <<'EOF'
nome,curso
Ana,Computacao
Bruno,Administracao
Carla,Computacao
Diego,Engenharia
EOF
```

O terminal recebe as linhas até encontrar `EOF` sozinho, sem espaços antes ou depois, e grava tudo em `alunos.csv`. As aspas em `'EOF'` fazem o conteúdo ser tratado literalmente. Esse arquivo é um **CSV**: cada linha é um registro e as vírgulas separam os campos. A primeira linha é o cabeçalho.

```bash
cat alunos.csv
head -n 2 alunos.csv
tail -n 2 alunos.csv
wc -l alunos.csv
tail -n +2 alunos.csv | wc -l
grep 'Computacao' alunos.csv
grep 'Computacao' alunos.csv | wc -l
```

| Comando | Resultado esperado neste exemplo |
| --- | --- |
| `head -n 2 alunos.csv` | Cabeçalho e registro da Ana |
| `tail -n 2 alunos.csv` | Registros da Carla e do Diego |
| `wc -l alunos.csv` | `5 alunos.csv`: um cabeçalho e quatro alunos |
| `tail -n +2 alunos.csv \| wc -l` | `4`: começa na segunda linha para excluir o cabeçalho |
| `grep 'Computacao' alunos.csv` | Registros da Ana e da Carla |
| `grep 'Computacao' alunos.csv \| wc -l` | `2`: conta as linhas selecionadas pelo filtro |

> `wc -l` conta caracteres de quebra de linha. Se a última linha de um arquivo não terminar com uma quebra, ela não entra nessa contagem. Os arquivos desta prática têm todas as linhas terminadas corretamente. O exemplo CSV usa uma linha por registro, sem campos com quebras de linha internas.

**3.4 Buscar e filtrar**

| Comando | O que faz |
| --- | --- |
| `grep "nuvem" notas.txt` | Mostra as linhas que contêm a palavra |
| `grep -ri "listen" /etc/ssh` | Busca em todos os arquivos da pasta, sem diferenciar maiúsculas |
| `find ~ -name "*.txt"` | Procura arquivos `.txt` na sua pasta pessoal |
| `history` | Lista os comandos que você já digitou |
| `comando \| less` | O `\|` (pipe) envia a saída de um comando para outro, ex.: `ls -la /etc \| less` |

**3.5 Recursos da máquina e processos**

| Comando | O que faz |
| --- | --- |
| `df -h` | Espaço em disco usado e livre (os 8 GiB) |
| `du -sh ~` | Quanto a sua pasta ocupa |
| `free -h` | Memória RAM usada e livre (cerca de 1 GiB) |
| `nproc` | Quantidade de CPUs |
| `top` ou `htop` | Processos em tempo real (`q` para sair) |
| `ps aux` | Lista todos os processos em execução |
| `ip a` | Mostra os endereços de rede (o IP privado da VPC) |
| `ss -tlnp` | Mostra as portas abertas esperando conexão |

**3.6 Usuários, permissões e pacotes**

| Comando | O que faz |
| --- | --- |
| `sudo comando` | Executa como administrador (root). Use só quando necessário |
| `ls -l notas.txt` | Mostra dono e permissões (`rw-r--r--` = dono lê/escreve, os outros só leem) |
| `chmod 600 notas.txt` | Deixa o arquivo legível só pelo dono |
| `sudo apt update` | Atualiza a lista de pacotes disponíveis |
| `sudo apt install pacote` | Instala um programa |
| `sudo apt remove pacote` | Remove um programa |
| `man ls` ou `ls --help` | Manual do comando (`q` para sair) |
| `clear` ou **Ctrl+L** | Limpa a tela |
| **Ctrl+C** | Interrompe o comando que está rodando |
| **Tab** | Completa nomes de comandos e arquivos — use sempre, evita erros de digitação |
| **↑ / ↓** | Navega pelos comandos anteriores |
| `exit` | Encerra a sessão SSH |

**3.7 Regras de segurança no terminal**

- **Nunca** execute `sudo rm -rf /` nem variações com `*` em pastas do sistema: apaga o servidor inteiro.
- Não cole comandos da internet sem entender o que fazem, principalmente os do tipo `curl ... | sudo bash`.
- Não use `chmod 777`: dá permissão total para qualquer usuário.
- Não crie senhas fracas nem desative o login por chave do SSH.
- Não abra portas novas no Security Group sem o professor pedir.
- Errou algo grave? Pare a instância e avise o professor; na nuvem é mais seguro recriar do que “consertar no escuro”.

**3.8 Exercícios simples no terminal EC2**

Faça os exercícios depois da prática guiada da seção 3.3. Use **somente arquivos da pasta `~/aula-ec2`**. Tente resolver usando as tabelas antes de consultar as respostas.

1. **Localização e navegação:** mostre seu usuário, entre em `~/aula-ec2/pratica`, exiba o caminho completo e liste os arquivos, incluindo os ocultos. Suba uma pasta e volte para `pratica`.
2. **Texto e contagem:** crie `tarefas.txt` com três linhas: `Criar pasta`, `Criar arquivo` e `Contar linhas`. Mostre o conteúdo e conte as linhas. Acrescente `Fazer backup` sem apagar as anteriores e confirme que agora existem quatro linhas.
3. **Dados e filtro:** usando `alunos.csv`, conte quantos alunos existem sem incluir o cabeçalho. Salve somente os registros de Computacao em `computacao.txt`, mostre esse arquivo e confirme que ele tem duas linhas.
4. **Cópia e renomeação:** crie uma subpasta `backup`, copie `tarefas.txt` para ela e renomeie a cópia para `tarefas-copia.txt`. Confira que o original e a cópia existem. Mostre as duas primeiras linhas da cópia.
5. **Busca e permissões:** encontre os arquivos `.txt` dentro da pasta `pratica`, incluindo suas subpastas. Altere a permissão da cópia para `600` e confira o resultado com `ls -l`.
6. **Remoção controlada:** crie um arquivo vazio chamado `descartavel.txt`, confira que ele existe e remova-o com confirmação (`rm -i`). Não remova os arquivos dos outros exercícios.
7. **Relatório do servidor:** em `~/aula-ec2/sobre.txt`, escreva seu nome, acrescente a data, a saída de `df -h` e a saída de `free -h`. Mostre o conteúdo, o tamanho e a quantidade de linhas do relatório.

**Conferência das respostas — execute no terminal da EC2**

Os comandos abaixo são uma possível solução. Os comandos com `>` recriam o conteúdo dos arquivos indicados.

```bash
# 1. Localização e navegação
whoami
cd ~/aula-ec2/pratica
pwd
ls -la
cd ..
cd pratica

# 2. Texto e contagem: primeiro 3 linhas, depois 4
printf 'Criar pasta\nCriar arquivo\nContar linhas\n' > tarefas.txt
cat tarefas.txt
wc -l tarefas.txt
printf 'Fazer backup\n' >> tarefas.txt
wc -l tarefas.txt

# 3. Dados e filtro: 4 alunos e 2 registros de Computacao
tail -n +2 alunos.csv | wc -l
grep 'Computacao' alunos.csv > computacao.txt
cat computacao.txt
wc -l computacao.txt

# 4. Cópia e renomeação
mkdir -p backup
cp tarefas.txt backup/tarefas.txt
mv backup/tarefas.txt backup/tarefas-copia.txt
ls -l tarefas.txt backup/tarefas-copia.txt
head -n 2 backup/tarefas-copia.txt

# 5. Busca e permissões: a cópia deve mostrar -rw-------
find . -type f -name '*.txt'
chmod 600 backup/tarefas-copia.txt
ls -l backup/tarefas-copia.txt

# 6. Remoção: responda y para confirmar
touch descartavel.txt
ls -l descartavel.txt
rm -i descartavel.txt

# 7. Relatório: substitua Seu Nome pelo seu nome
cd ~/aula-ec2
printf 'Aluno: Seu Nome\n' > sobre.txt
date >> sobre.txt
df -h >> sobre.txt
free -h >> sobre.txt
cat sobre.txt
ls -lh sobre.txt
wc -l sobre.txt
```

A quantidade de linhas de `sobre.txt` varia conforme os discos montados e a saída dos comandos no servidor. **Entrega sugerida:** mostre ao professor `tarefas.txt`, `computacao.txt`, a cópia com permissão `600` e o relatório `sobre.txt`.

## Parte 4 — Instalar Apache e PostgreSQL (sem expor à internet)

Os dois serviços vão escutar apenas no endereço local `127.0.0.1` (localhost), e o Security Group continua liberando só a porta 22. Você vai testar tudo de dentro do servidor ou por um túnel SSH.

```text
Console AWS ──EC2 Instance Connect (porta 22)──▶ EC2
                                                 ├─ Apache      127.0.0.1:80
                                                 └─ PostgreSQL  127.0.0.1:5432
Internet ── porta 80 / 5432 ──✖ bloqueado pelo Security Group
```

**4.1 Instalar o Apache**

```bash
sudo apt install -y apache2
```

Por segurança extra, faça o Apache escutar só no localhost. Abra o arquivo de portas:

```bash
sudo nano /etc/apache2/ports.conf
```

Troque a linha `Listen 80` por:

```apache
Listen 127.0.0.1:80
```

Salve (**Ctrl+O**, Enter, **Ctrl+X**), valide e reinicie:

```bash
sudo apache2ctl configtest     # deve responder: Syntax OK
sudo systemctl restart apache2
```

Teste de dentro do servidor:

```bash
curl -I http://localhost       # resposta esperada: HTTP/1.1 200 OK
```

Personalize a página de teste:

```bash
echo "<h1>Servidor de $(whoami) na AWS</h1>" | sudo tee /var/www/html/index.html
curl http://localhost
```

**4.2 Opcional: ver a página no seu navegador com túnel SSH local**

Se estiver usando apenas o terminal do console, o teste com `curl` da seção 4.1 é suficiente: avance para a seção 4.3. O `localhost` do servidor não é o `localhost` do seu computador.

Para esta atividade opcional, prepare o SSH local conforme a seção 2.4 e substitua o IP abaixo pelo da instância.

O túnel leva a porta 80 do servidor até a porta 8080 do **seu** computador, pelo canal criptografado do SSH, sem abrir nada na internet. Em um **novo terminal no seu computador**:

```bash
ssh -i ~/.ssh/chave-seunome.pem -L 8080:localhost:80 ubuntu@3.85.120.44
```

Com essa janela aberta, acesse `http://localhost:8080` no seu navegador. Ao fechar o SSH, o acesso acaba.

**4.3 Instalar o PostgreSQL**

```bash
sudo apt install -y postgresql
```

No Ubuntu, o PostgreSQL já vem escutando só em `localhost` (`listen_addresses = 'localhost'`). **Não altere isso** nesta aula. Confira:

```bash
sudo -u postgres psql -c "SHOW listen_addresses;"
```

**4.4 Criar um usuário e um banco de teste**

```bash
sudo -u postgres psql
```

Dentro do `psql` (prompt `postgres=#`):

```sql
CREATE USER aluno WITH PASSWORD 'troque-esta-senha';
CREATE DATABASE aula OWNER aluno;
\q
```

Conecte com o novo usuário e crie uma tabela:

```bash
psql -h localhost -U aluno -d aula
```

```sql
CREATE TABLE alunos (id SERIAL PRIMARY KEY, nome TEXT NOT NULL);
INSERT INTO alunos (nome) VALUES ('Maria'), ('João');
SELECT * FROM alunos;
\q
```

**Comandos úteis dentro do `psql`**

| Comando | O que faz |
| --- | --- |
| `\l` | Lista os bancos |
| `\c aula` | Conecta ao banco `aula` |
| `\dt` | Lista as tabelas do banco atual |
| `\d alunos` | Mostra a estrutura da tabela |
| `\du` | Lista os usuários (roles) |
| `\q` | Sai do `psql` |

## Parte 5 — Verificar, subir e derrubar os serviços

No Ubuntu, os serviços são controlados pelo `systemctl`. Os nomes são `apache2` e `postgresql`.

| Ação | Apache | PostgreSQL |
| --- | --- | --- |
| Ver status detalhado | `sudo systemctl status apache2` | `sudo systemctl status postgresql` |
| Está ativo? (resposta curta) | `systemctl is-active apache2` | `systemctl is-active postgresql` |
| Subir (iniciar) | `sudo systemctl start apache2` | `sudo systemctl start postgresql` |
| Derrubar (parar) | `sudo systemctl stop apache2` | `sudo systemctl stop postgresql` |
| Reiniciar (após mudar configuração) | `sudo systemctl restart apache2` | `sudo systemctl restart postgresql` |
| Recarregar config sem derrubar | `sudo systemctl reload apache2` | `sudo systemctl reload postgresql` |
| Iniciar junto com a máquina? | `systemctl is-enabled apache2` | `systemctl is-enabled postgresql` |
| Não iniciar no boot | `sudo systemctl disable apache2` | `sudo systemctl disable postgresql` |
| Voltar a iniciar no boot | `sudo systemctl enable apache2` | `sudo systemctl enable postgresql` |
| Ver os últimos logs | `sudo journalctl -u apache2 -n 20` | `sudo journalctl -u postgresql -n 20` |

**Como ler o `status`:** procure a linha `Active:`.

- `Active: active (running)` — o serviço está no ar.
- `Active: inactive (dead)` — o serviço está parado.
- `Active: failed` — tentou subir e deu erro; veja os logs com `journalctl`.

Aperte **q** para sair da tela de status.

**Confirmar que está escutando só no localhost:**

```bash
sudo ss -tlnp | grep -E ':80|:5432'
```

A saída esperada mostra `127.0.0.1:80` e `127.0.0.1:5432` (ou `[::1]:5432`). Se aparecer `0.0.0.0:80` ou `*:80`, refaça o passo 4.1.

**Prática guiada: derrube e suba o Apache**

```bash
sudo systemctl stop apache2
systemctl is-active apache2    # inactive
curl -I http://localhost       # Failed to connect: o servidor caiu

sudo systemctl start apache2
systemctl is-active apache2    # active
curl -I http://localhost       # HTTP/1.1 200 OK
```

Faça o mesmo com o PostgreSQL, testando com `psql -h localhost -U aluno -d aula -c "SELECT 1;"`.

**Encerrar a prática (economiza memória e evita serviços esquecidos):**

```bash
sudo systemctl stop apache2 postgresql
sudo systemctl disable apache2 postgresql
```

Na próxima aula, suba de novo com `sudo systemctl start apache2 postgresql`.

## Parte 6 — Desligar a instância ao final da aula

Parar os serviços não basta: o que gera consumo é a **instância ligada**. Ao terminar, pare a máquina pelo console.

| Ação no console | O que acontece | Quando usar |
| --- | --- | --- |
| **Parar instância (Stop instance)** | Desliga a máquina. O disco e seus arquivos continuam. Para de consumir horas de CPU e o IP público é liberado. O disco de 8 GiB continua existindo (dentro dos 30 GiB gratuitos) | **Ao fim de cada aula** |
| **Iniciar instância (Start instance)** | Liga de novo, com tudo como você deixou. **O IP público muda** | No começo da próxima aula |
| **Reinicializar (Reboot)** | Reinicia sem desligar | Raramente necessário |
| ⚠ **Encerrar instância (Terminate instance)** | **Apaga** a máquina e o disco. Não tem volta | Ao fim da disciplina, ou se quebrar algo e quiser recomeçar |

**6.1 Parar a instância**

1. Digite `exit` para sair do terminal do console ou da sessão SSH local. Isso apenas encerra a conexão.
2. No console: **EC2** → **Instâncias (Instances)** → marque `linux-seunome`.
3. **Estado da instância (Instance state)** → **Parar instância (Stop instance)** → **Parar (Stop)**.
4. Aguarde **Estado da instância: Interrompida (Instance state: Stopped)** antes de fechar o navegador.

**6.2 Rede de segurança: desligamento automático**

O desligamento já deve ter sido agendado **logo no primeiro acesso**, na seção 2.2. Repita essa ação no começo de cada aula, após iniciar a instância:

```bash
sudo shutdown -h +180     # desliga em 180 minutos (3 horas)
```

Não é preciso cancelar esse agendamento para parar a máquina antes pelo console. Se precisar estender a aula, siga o cancelamento e novo agendamento da seção 2.2. Confirme **Comportamento de desligamento (Shutdown behavior): Parar (Stop)** para preservar a instância e seu volume EBS.

**6.3 Conferir consumo**

- **Faturamento e gerenciamento de custos (Billing and Cost Management)** → **Nível gratuito (Free Tier)** / **Créditos (Credits)**: mostra quanto dos créditos foi usado.
- Verifique que não há instâncias **Em execução (Running)** esquecidas em **outras regiões**: o painel **Visualização global do EC2 (EC2 Global View)** (pesquise no topo) lista todas.
- Confira em **IPs elásticos (Elastic IPs)** e **Volumes (Volumes)** que não há nada além do disco da sua instância.

**6.4 Fim da disciplina: remover tudo**

1. Use **Encerrar instância (Terminate instance)**; o disco é apagado junto.
2. Em **Grupos de segurança (Security Groups)**, apague `sg-linux-seunome`.
3. Em **Pares de chaves (Key Pairs)**, apague `chave-seunome` e o arquivo `.pem` do seu computador.

## Erros comuns

| Sintoma | Causa provável | Como resolver |
| --- | --- | --- |
| Terminal do console não conecta | Regra do EC2 Instance Connect ausente, rede sem acesso público ou permissões IAM insuficientes | Confira a lista de prefixos regional na porta 22, IP público, rota para internet e permissões para EC2 Instance Connect; use o usuário `ubuntu` |
| `Connection timed out` no SSH | Seu IP mudou, instância parada ou IP público antigo | Atualize a regra **Meu IP (My IP)** no grupo de segurança; confira **Em execução (Running)** e copie o IP novo |
| `Permission denied (publickey)` | Usuário errado ou chave errada | Use `ubuntu@` e o `.pem` criado com esta instância |
| `UNPROTECTED PRIVATE KEY FILE` | Permissões da chave abertas demais | Refaça a preparação da chave na seção 2.4 (`chmod 400` ou `icacls`) |
| `REMOTE HOST IDENTIFICATION HAS CHANGED` | O IP foi reaproveitado por outra máquina | `ssh-keygen -R 3.85.120.44` (com o IP) e conecte de novo |
| `curl: Failed to connect to localhost port 80` | Apache parado ou com erro | `sudo systemctl status apache2` e `sudo apache2ctl configtest` |
| `Job for apache2.service failed` | Erro de digitação no `ports.conf` | Corrija para `Listen 127.0.0.1:80` e rode `configtest` |
| `psql: FATAL: password authentication failed` | Senha ou usuário errado | Redefina: `sudo -u postgres psql -c "ALTER USER aluno PASSWORD 'nova';"` |
| `bind: Address already in use` no túnel | A porta 8080 já está em uso no seu PC | Use outra porta local: `-L 8081:localhost:80` |
| `apt` travado em `Could not get lock` | Atualização automática rodando | Aguarde 2 a 5 minutos e repita |

## Checklist da aula

- [ ] Instância `t3.micro` Ubuntu 24.04 com selo **Qualificado para o nível gratuito (Free tier eligible)** e disco de 8 GiB
- [ ] Grupo de segurança com **SSH (22) para EC2 Instance Connect** e, se usar SSH local, **Meu IP**
- [ ] Acesso pelo terminal do console funcionando (ou SSH local opcional)
- [ ] **Comportamento de desligamento: Parar (Stop)** confirmado e `sudo shutdown -h +180` executado logo no primeiro acesso
- [ ] Sistema atualizado
- [ ] Exercício de comandos feito (`~/aula-ec2/sobre.txt`)
- [ ] Arquivos de texto e CSV criados; contagem com `wc -l` e exercícios da seção 3.8 realizados
- [ ] Apache respondendo `200 OK` em `http://localhost` e escutando só em `127.0.0.1:80`
- [ ] Banco `aula` com a tabela `alunos` criada no PostgreSQL
- [ ] Serviços derrubados e subidos com `systemctl` pelo menos uma vez
- [ ] Serviços parados ao final (`stop` + `disable`)
- [ ] **Instância Interrompida (Stopped)** no console antes de sair

## Fontes

- [AWS — Nível gratuito: escolhendo um plano](https://docs.aws.amazon.com/pt_br/awsaccountbilling/latest/aboutv2/free-tier-plans.html)
- [AWS — Tutorial: comece a usar instâncias Linux do EC2](https://docs.aws.amazon.com/pt_br/AWSEC2/latest/UserGuide/EC2_GetStarted.html)
- [AWS — Conectar à instância Linux usando SSH](https://docs.aws.amazon.com/pt_br/AWSEC2/latest/UserGuide/connect-linux-inst-ssh.html)
- [AWS — Pré-requisitos do EC2 Instance Connect](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-instance-connect-prerequisites.html)
- [AWS — Conectar pelo EC2 Instance Connect](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-instance-connect-methods.html)
- [AWS — Comportamento de desligamento da instância](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/Using_ChangingInstanceInitiatedShutdownBehavior.html)
- [AWS — Parar e iniciar instâncias](https://docs.aws.amazon.com/pt_br/AWSEC2/latest/UserGuide/Stop_Start.html)
- [Ubuntu Server — Apache](https://documentation.ubuntu.com/server/how-to/web-services/install-apache2/)
- [Ubuntu Server — PostgreSQL](https://documentation.ubuntu.com/server/how-to/databases/install-postgresql/)
