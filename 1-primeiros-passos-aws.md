# Primeiros Passos na AWS

**Da criação da conta gratuita à publicação de um site**

*Material introdutório para uso em sala de aula*

> **Idioma da console:** os nomes de telas, campos e botões aparecem primeiro em português e, entre parênteses, em inglês. Exemplo: **Instâncias (Instances)**.

## Objetivo

Neste tutorial, você aprenderá a:

- Criar uma conta gratuita na AWS.
- Conhecer o AWS Management Console.
- Entender o conceito de Região.
- Localizar os principais serviços.
- Conhecer EC2, S3, RDS, IAM, CloudFront, Route 53 e VPC.
- Entender quais serviços são necessários para colocar um site no ar.
- Tomar cuidados para evitar custos inesperados.

## 1. O que é a AWS?

A Amazon Web Services (AWS) é uma plataforma de computação em nuvem que permite utilizar recursos de tecnologia pela Internet. Em vez de comprar e manter servidores físicos, podemos criar recursos virtuais conforme a necessidade.

| **Serviço** | **Para que serve**                              |
|-------------|-------------------------------------------------|
| EC2         | Criar servidores virtuais                       |
| S3          | Armazenar arquivos e hospedar conteúdo estático |
| RDS         | Criar bancos de dados gerenciados               |
| IAM         | Gerenciar usuários e permissões                 |
| CloudFront  | Distribuir conteúdo por CDN                     |
| Route 53    | Gerenciar DNS e domínios                        |
| VPC         | Criar e configurar redes virtuais               |

Durante as aulas, nosso foco será entender como esses serviços podem trabalhar juntos para disponibilizar uma aplicação na Internet.

## 2. Criando uma conta gratuita

Acesse a página oficial: [AWS Free Tier](https://aws.amazon.com/pt/free/)

Clique em “Criar uma conta gratuita”. Para contas novas, a AWS permite escolher entre Plano Gratuito e Plano Pago. Para as atividades de aprendizado, escolha o Plano Gratuito (Free account plan).

Para contas novas, o Plano Gratuito é voltado a aprendizado, experimentação e provas de conceito. A disponibilidade, duração, créditos e condições podem mudar; confirme sempre as informações exibidas pela AWS durante o cadastro.

> **Importante:** as regras do AWS Free Tier podem mudar. Sempre verifique no Console quais serviços e créditos estão disponíveis em sua conta.

## 3. Informações necessárias para o cadastro

Durante a criação da conta, a AWS solicitará informações para verificar a identidade e configurar a conta. Normalmente serão solicitados:

- Endereço de e-mail.
- Nome.
- Telefone.
- Endereço.
- Senha.
- Informações necessárias para verificação da conta e faturamento.

Siga as instruções apresentadas pela própria AWS durante o cadastro. A AWS também poderá solicitar uma verificação por telefone. Ao finalizar, aguarde a confirmação da criação da conta.

## 4. Entrando no AWS Management Console

Depois que a conta estiver ativa, acesse: [AWS Management Console](https://console.aws.amazon.com/)

O AWS Management Console será nossa principal interface durante as primeiras aulas. No início, a quantidade de serviços pode parecer grande. Não precisamos conhecer todos: vamos começar pelos recursos essenciais para colocar uma aplicação Web no ar.

## 5. Conhecendo o painel da AWS

### Pesquisa

Na parte superior existe uma caixa de pesquisa. Ela será uma das ferramentas mais utilizadas. Experimente pesquisar por EC2, S3, RDS e IAM.

### Serviços

Os serviços são organizados em categorias como **Computação (Compute)**, **Armazenamento (Storage)**, **Banco de dados (Database)**, **Redes e entrega de conteúdo (Networking & Content Delivery)**, **Segurança, identidade e conformidade (Security, Identity, & Compliance)** e **Ferramentas do desenvolvedor (Developer Tools)**.

### Região

Na parte superior do Console aparece a Região AWS selecionada. Exemplos: **Leste dos EUA (Norte da Virgínia) (US East — N. Virginia)** e **América do Sul (São Paulo) (South America — São Paulo)**. Muitos recursos, como instâncias EC2, são regionais. Se você criar um recurso em uma Região e selecionar outra depois, ele poderá não aparecer no painel.

Durante as atividades em sala, utilize sempre a Região indicada pelo professor.

### Conta

No canto superior direito ficam opções relacionadas à conta, segurança e faturamento.

## 6. Primeiro cuidado: acompanhe seus créditos e custos

Antes de criar servidores, pesquise no Console por **Faturamento e gerenciamento de custos (Billing and Cost Management)** e conheça estas áreas:

- **Faturamento (Billing)** — informações relacionadas à cobrança.
- **Créditos (Credits)** — créditos disponíveis na conta.
- **Orçamentos (Budgets)** — acompanhamento de gastos e configuração de alertas.

Regra para nossas aulas: não crie recursos diferentes daqueles solicitados durante a atividade e remova os recursos de teste que não serão mais utilizados.

## 7. EC2 — nosso servidor na nuvem

Amazon EC2 (Elastic Compute Cloud) permite criar máquinas virtuais na nuvem. Podemos imaginar uma instância EC2 como um computador funcionando dentro de um datacenter da AWS.

- Linux.
- Apache ou Nginx.
- PHP.
- Node.js.
- Java.
- Aplicações Web e APIs.

Fluxo simplificado:

```text
Internet → EC2 → Servidor Web → Aplicação
```

No painel do EC2, observe principalmente **Instâncias (Instances)**, **Tipos de instância (Instance Types)**, **Grupos de segurança (Security Groups)**, **IPs elásticos (Elastic IPs)** e **Volumes (Volumes)**. Não crie uma instância ainda, a menos que seja solicitado na atividade.

## 8. Grupos de segurança (Security Groups) — o firewall da AWS

Grupos de segurança (Security Groups) controlam o tráfego permitido para recursos como instâncias EC2. Para um servidor Web, portas comuns incluem:

| **Porta** | **Serviço** |
|-----------|-------------|
| 22        | SSH         |
| 80        | HTTP        |
| 443       | HTTPS       |

Resumo: EC2 = servidor; Security Group = controla o acesso ao servidor.

## 9. S3 — armazenamento de arquivos

Amazon S3 (Simple Storage Service) é utilizado para armazenar objetos e arquivos, como imagens, documentos, backups, CSS, JavaScript e páginas HTML. Os arquivos são organizados em Buckets.

Exemplo: um bucket “meu-site” pode conter index.html, css/, js/ e imagens/. Um site somente com HTML, CSS e JavaScript pode ser disponibilizado com S3 e CloudFront, sem manter um servidor EC2 tradicional.

## 10. RDS — banco de dados

Amazon RDS (Relational Database Service) permite criar bancos de dados relacionais gerenciados. Entre as opções estão PostgreSQL, MySQL e MariaDB.

Arquitetura simplificada: Usuário → EC2 → Aplicação → RDS. Exemplo: Navegador → Apache/PHP → PostgreSQL.

## 11. IAM — usuários e permissões

IAM (Identity and Access Management) controla quem pode fazer o quê dentro da AWS. Os principais conceitos são usuários, grupos, roles, policies e permissões.

Boa prática: não utilizar o usuário root da conta para atividades rotineiras. Use-o somente quando uma operação realmente exigir esse nível de acesso.

## 12. VPC — nossa rede na AWS

Amazon VPC (Virtual Private Cloud) representa uma rede virtual dentro da AWS. Ela envolve redes, sub-redes, endereços IP, tabelas de roteamento, gateways e comunicação entre recursos. Nas primeiras atividades, utilizaremos principalmente as configurações padrão fornecidas pela AWS.

## 13. CloudFront — distribuição do site

Amazon CloudFront é o serviço de CDN da AWS. Uma CDN ajuda a distribuir conteúdo para os usuários. Uma arquitetura comum para sites estáticos é: Usuário → CloudFront → S3.

## 14. Route 53 — domínio e DNS

Amazon Route 53 é o serviço de DNS da AWS. Ele pode relacionar um domínio aos recursos da aplicação.

Exemplos: www.meusite.com.br → Route 53 → CloudFront → S3; ou www.meusite.com.br → Route 53 → EC2.

Registrar um domínio possui custo próprio e não é necessário para as primeiras atividades.

## 15. Como esses serviços trabalham juntos?

Para uma aplicação PHP com PostgreSQL, uma arquitetura simplificada poderia ser:

```text
Internet → Route 53 (DNS) → EC2 (Linux + Apache + PHP) → RDS (PostgreSQL)
```

Arquivos adicionais podem ficar no S3; o CloudFront pode distribuir conteúdo estático; o IAM controla permissões; a VPC organiza a rede; e os Security Groups controlam o acesso.

## 16. E se o site for apenas HTML, CSS e JavaScript?

Nesse caso, podemos simplificar bastante: Internet → CloudFront → S3. Essa é uma arquitetura interessante para o primeiro exercício de publicação.

## 17. Serviços que você deve memorizar inicialmente

| **Serviço**    | **Pense nele como**   |
|----------------|-----------------------|
| EC2            | Servidor              |
| S3             | Arquivos              |
| RDS            | Banco de dados        |
| IAM            | Usuários e permissões |
| VPC            | Rede                  |
| Security Group | Firewall              |
| CloudFront     | CDN                   |
| Route 53       | DNS                   |

## 18. Exercício de reconhecimento do Console

Entre no AWS Management Console e, utilizando somente a pesquisa, localize:

1. EC2
2. S3
3. RDS
4. IAM
5. VPC
6. CloudFront
7. Route 53
8. Faturamento e gerenciamento de custos (Billing and Cost Management)

Não crie recursos ainda. O objetivo é aprender a navegar pelo Console e identificar a finalidade de cada serviço.

## 19. Próxima atividade

Uma sequência progressiva recomendada é:

1. Criar uma página HTML simples.
2. Criar um Bucket S3.
3. Enviar o site para o S3.
4. Disponibilizar o site.
5. Acessar o site pela Internet.

Depois, podemos evoluir para EC2 → Linux → Apache → PHP → aplicação Web e, posteriormente, EC2 + RDS PostgreSQL.

## Regra de ouro da aula

> **“Criou um recurso para teste? Saiba onde ele está, quanto pode custar e como removê-lo quando terminar.”**

Na nuvem, aprender a criar recursos é importante. Aprender a monitorar e remover esses recursos também faz parte da atividade.

## Referências oficiais

- [AWS Free Tier](https://aws.amazon.com/pt/free/)
- [AWS Free Tier — documentação](https://docs.aws.amazon.com/awsaccountbilling/latest/aboutv2/free-tier.html)
- [AWS Management Console](https://console.aws.amazon.com/)
- [Regiões e Zonas de Disponibilidade do EC2](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/using-regions-availability-zones.html)
- [IAM — primeiros passos](https://docs.aws.amazon.com/IAM/latest/UserGuide/getting-started.html)
- [Route 53 — primeiros passos](https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/getting-started.html)
