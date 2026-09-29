# Tutorial — Servidor Linux no Amazon EC2

Computação em Nuvem · 29/09/2026 · Prof. Felipe

## Objetivo da aula

Nesta aula você vai criar um servidor Linux (Ubuntu) na AWS com a menor configuração possível, acessá-lo por SSH, praticar comandos básicos e instalar Apache e PostgreSQL. **Nada disso fica exposto na internet**, e a máquina é desligada no final.

**Pré-requisitos**

- Conta AWS no **plano gratuito**, com MFA e **Zero spend budget** ativos (veja o tutorial de S3, Parte 3).
- Região **us-east-1 (Norte da Virgínia)**, a mesma das aulas anteriores.
- Terminal no seu computador: **PowerShell** (Windows 10/11 já tem o comando `ssh`), **Terminal** (Mac/Linux) ou o **Git Bash**.

**As 5 regras de custo zero desta aula**

| Regra | Por quê |
| --- | --- |
| Use **apenas** tipo de instância com o selo **Free tier eligible** (recomendado: `t3.micro`) | Tipos maiores consomem os créditos muito mais rápido |
| Disco de **8 GiB gp3** (o padrão) e **uma** instância por aluno | O gratuito cobre até 30 GiB de EBS por mês somando todos os discos |
| **Não** crie Elastic IP, Load Balancer, NAT Gateway nem RDS nesta aula | São cobrados por hora, mesmo parados |
| Libere no firewall **só a porta 22 (SSH) para o seu IP** | Apache e PostgreSQL ficam acessíveis apenas de dentro da máquina |
| **Pare (Stop) a instância ao terminar** | Instância ligada consome horas e o IP público é cobrado por hora (US$ 0,005/h) enquanto ela roda |

> No plano gratuito, o uso de EC2 é descontado dos seus créditos (US$ 100 iniciais). Uma `t3.micro` ligada só durante as aulas consome centavos; esquecida ligada o mês inteiro, consome vários dólares. **Ligou, usou, desligou.**

## Parte 1 — Criar a instância EC2

Você vai lançar uma máquina Ubuntu `t3.micro` com 8 GiB de disco e acesso SSH liberado só para o seu IP.

1. No console, confirme a região **us-east-1** no canto superior direito.
2. Pesquise **EC2** na barra do topo → **Instances** → **Launch instances**.
3. Preencha a página conforme a tabela:

| Campo | Valor | Observação |
| --- | --- | --- |
| **Name** | `linux-seunome` | Ex.: `linux-maria` |
| **Application and OS Images (AMI)** | **Ubuntu Server 24.04 LTS**, arquitetura **64-bit (x86)** | Deve exibir **Free tier eligible** |
| **Instance type** | **t3.micro** | 2 vCPU, 1 GiB de RAM; confira o selo **Free tier eligible** |
| **Key pair (login)** | **Create new key pair** → nome `chave-seunome`, tipo **ED25519**, formato **.pem** | O arquivo baixa **uma única vez**; guarde-o bem |
| **Network settings** → **Edit** | VPC padrão; **Auto-assign public IP: Enable** | Necessário para o SSH |
| **Firewall (security groups)** | **Create security group** → nome `sg-linux-seunome` | |
| Regra de entrada | **SSH, porta 22, Source type: My IP** | **Não** use `0.0.0.0/0` (Anywhere) |
| Outras regras (HTTP/HTTPS) | **Deixe desmarcadas** | O Apache não ficará exposto |
| **Configure storage** | **8 GiB, gp3** | Não aumente |
| **Advanced details** | Deixe o padrão | **Shutdown behavior: Stop** (padrão) |

4. Confira o painel **Summary** à direita: **Number of instances: 1**.
5. Clique em **Launch instance** → **View all instances**.
6. Aguarde **Instance state: Running** e **Status check: 3/3 checks passed** (1 a 3 minutos).
7. Clique na instância e copie o **Public IPv4 address** (ex.: `3.85.120.44`).

> **A chave `.pem` é a senha do seu servidor.** Não envie no grupo da turma, não coloque no GitHub e não salve em pasta compartilhada. Se perder, não há como baixar de novo.

**Mudou de rede (casa → faculdade)?** O “My IP” muda. Em **Security Groups** → `sg-linux-seunome` → **Edit inbound rules** → na regra SSH, selecione **My IP** de novo → **Save rules**.

## Parte 2 — Acessar por SSH

Você vai se conectar com o usuário `ubuntu`, usando a chave `.pem` e o IP público da instância.

**2.1 Guardar a chave com permissão restrita**

O SSH recusa chaves que outros usuários do computador possam ler.

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

**2.2 Conectar**

Troque o IP pelo da sua instância:

```bash
ssh -i ~/.ssh/chave-seunome.pem ubuntu@3.85.120.44
```

(No PowerShell: `ssh -i $env:USERPROFILE\.ssh\chave-seunome.pem ubuntu@3.85.120.44`)

1. Na primeira conexão aparece `Are you sure you want to continue connecting (yes/no)?`. Digite `yes` e Enter.
2. O prompt muda para algo como `ubuntu@ip-172-31-20-15:~$`. **Você está dentro do servidor.**
3. Para sair, digite `exit`.

**2.3 Alternativa sem instalar nada: EC2 Instance Connect**

Se o SSH do seu computador estiver bloqueado (rede da faculdade, por exemplo): selecione a instância → **Connect** → aba **EC2 Instance Connect** → usuário `ubuntu` → **Connect**. Abre um terminal no navegador.

> O Instance Connect usa IPs da própria AWS. Se ele falhar com a regra **My IP**, adicione temporariamente no Security Group a origem indicada pelo console na tela de erro e remova-a ao final da aula.

**2.4 Primeira tarefa no servidor: atualizar o sistema**

```bash
sudo apt update && sudo apt upgrade -y
```

Se aparecer uma tela roxa perguntando sobre serviços a reiniciar, aperte **Enter** para aceitar o padrão.

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
| `nano notas.txt` | Editor de texto no terminal: **Ctrl+O** salva, **Ctrl+X** sai |
| `cp notas.txt copia.txt` | Copia um arquivo |
| `mv copia.txt aula/` | Move (ou renomeia) um arquivo |
| ⚠ `rm copia.txt` | Apaga o arquivo **sem lixeira** — não tem volta |
| ⚠ `rm -r aula` | Apaga a pasta e tudo dentro dela. Confira o caminho com `pwd` antes |

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

**Exercício rápido:** crie a pasta `~/aula-ec2`, dentro dela um arquivo `sobre.txt` com seu nome e a saída de `df -h` (`df -h >> sobre.txt`), mostre o conteúdo com `cat` e confira o tamanho com `ls -lh`.

## Parte 4 — Instalar Apache e PostgreSQL (sem expor à internet)

Os dois serviços vão escutar apenas no endereço local `127.0.0.1` (localhost), e o Security Group continua liberando só a porta 22. Você vai testar tudo de dentro do servidor ou por um túnel SSH.

```text
Seu computador ──SSH (porta 22, só o seu IP)──▶ EC2
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

**4.2 Ver a página no seu navegador com túnel SSH**

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
| **Stop instance** (parar) | Desliga a máquina. O disco e seus arquivos continuam. Para de consumir horas de CPU e o IP público é liberado. O disco de 8 GiB continua existindo (dentro dos 30 GiB gratuitos) | **Ao fim de cada aula** |
| **Start instance** (iniciar) | Liga de novo, com tudo como você deixou. **O IP público muda** | No começo da próxima aula |
| **Reboot** | Reinicia sem desligar | Raramente necessário |
| ⚠ **Terminate instance** (encerrar) | **Apaga** a máquina e o disco. Não tem volta | Ao fim da disciplina, ou se quebrar algo e quiser recomeçar |

**6.1 Parar a instância**

1. Digite `exit` para sair do SSH.
2. No console: **EC2** → **Instances** → marque `linux-seunome`.
3. **Instance state** → **Stop instance** → **Stop**.
4. Aguarde **Instance state: Stopped** antes de fechar o navegador.

**6.2 Rede de segurança: desligamento automático**

No começo de cada aula, agende o desligamento para o caso de esquecer. Dentro do servidor:

```bash
sudo shutdown -h +180     # desliga em 180 minutos (3 horas)
sudo shutdown -c          # cancela o agendamento, se precisar
```

Como o *Shutdown behavior* da instância é **Stop**, desligar pelo Linux equivale a parar pelo console: nada é apagado.

**6.3 Conferir consumo**

- **Billing and Cost Management** → **Free Tier** / **Credits**: mostra quanto dos créditos foi usado.
- Verifique que não há instâncias **Running** esquecidas em **outras regiões**: o painel **EC2 Global View** (pesquise no topo) lista todas.
- Confira em **Elastic IPs** e **Volumes** que não há nada além do disco da sua instância.

**6.4 Fim da disciplina: remover tudo**

1. **Terminate** a instância (o disco é apagado junto).
2. Em **Security Groups**, apague `sg-linux-seunome`.
3. Em **Key Pairs**, apague `chave-seunome` e o arquivo `.pem` do seu computador.

## Erros comuns

| Sintoma | Causa provável | Como resolver |
| --- | --- | --- |
| `Connection timed out` no SSH | Seu IP mudou, instância parada ou IP público antigo | Atualize a regra **My IP** no Security Group; confira **Running** e copie o IP novo |
| `Permission denied (publickey)` | Usuário errado ou chave errada | Use `ubuntu@` e o `.pem` criado com esta instância |
| `UNPROTECTED PRIVATE KEY FILE` | Permissões da chave abertas demais | Refaça o passo 2.1 (`chmod 400` ou `icacls`) |
| `REMOTE HOST IDENTIFICATION HAS CHANGED` | O IP foi reaproveitado por outra máquina | `ssh-keygen -R 3.85.120.44` (com o IP) e conecte de novo |
| `curl: Failed to connect to localhost port 80` | Apache parado ou com erro | `sudo systemctl status apache2` e `sudo apache2ctl configtest` |
| `Job for apache2.service failed` | Erro de digitação no `ports.conf` | Corrija para `Listen 127.0.0.1:80` e rode `configtest` |
| `psql: FATAL: password authentication failed` | Senha ou usuário errado | Redefina: `sudo -u postgres psql -c "ALTER USER aluno PASSWORD 'nova';"` |
| `bind: Address already in use` no túnel | A porta 8080 já está em uso no seu PC | Use outra porta local: `-L 8081:localhost:80` |
| `apt` travado em `Could not get lock` | Atualização automática rodando | Aguarde 2 a 5 minutos e repita |

## Checklist da aula

- [ ] Instância `t3.micro` Ubuntu 24.04 com selo **Free tier eligible** e disco de 8 GiB
- [ ] Security Group só com **SSH (22) para My IP**
- [ ] Acesso por SSH funcionando e sistema atualizado
- [ ] Exercício de comandos feito (`~/aula-ec2/sobre.txt`)
- [ ] Apache respondendo `200 OK` em `http://localhost` e escutando só em `127.0.0.1:80`
- [ ] Banco `aula` com a tabela `alunos` criada no PostgreSQL
- [ ] Serviços derrubados e subidos com `systemctl` pelo menos uma vez
- [ ] Serviços parados ao final (`stop` + `disable`)
- [ ] **Instância em Stopped** no console antes de sair

## Fontes

- [AWS — Nível gratuito: escolhendo um plano](https://docs.aws.amazon.com/pt_br/awsaccountbilling/latest/aboutv2/free-tier-plans.html)
- [AWS — Tutorial: comece a usar instâncias Linux do EC2](https://docs.aws.amazon.com/pt_br/AWSEC2/latest/UserGuide/EC2_GetStarted.html)
- [AWS — Conectar à instância Linux usando SSH](https://docs.aws.amazon.com/pt_br/AWSEC2/latest/UserGuide/connect-linux-inst-ssh.html)
- [AWS — Parar e iniciar instâncias](https://docs.aws.amazon.com/pt_br/AWSEC2/latest/UserGuide/Stop_Start.html)
- [Ubuntu Server — Apache](https://documentation.ubuntu.com/server/how-to/web-services/install-apache2/)
- [Ubuntu Server — PostgreSQL](https://documentation.ubuntu.com/server/how-to/databases/install-postgresql/)
