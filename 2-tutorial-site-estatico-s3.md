# Tutorial — Site estático no Amazon S3

Computação em Nuvem · 29/09/2026 · Prof. Felipe

> **Idioma da console:** os nomes de telas, campos e botões aparecem primeiro em português e, entre parênteses, em inglês. Exemplo: **Criar bucket (Create bucket)**.

## Objetivo da aula

Ao final desta aula, cada aluno terá o próprio site de perfil profissional publicado na internet a partir de um bucket do Amazon S3, com um endereço público que pode ser aberto em qualquer navegador.

O fluxo completo é: clonar o projeto de exemplo, personalizar o `index.html` no VS Code, criar um bucket S3, configurá-lo como site estático e enviar os arquivos para a raiz do bucket.

**O que você precisa antes de começar**

- Git instalado ([git-scm.com](https://git-scm.com/downloads)) — ou apenas um navegador, se preferir baixar o ZIP.
- VS Code instalado ([code.visualstudio.com](https://code.visualstudio.com/)).
- Um e-mail que você acesse na hora (a AWS envia código de verificação).
- Um celular para receber SMS/ligação de verificação.
- Um cartão de crédito ou débito internacional: a AWS exige o cartão no cadastro, mesmo no plano gratuito, apenas para verificar a identidade.
- Uma foto sua em formato `.jpg` ou `.png` (opcional, mas recomendado).

> **Atenção:** o site ficará público na internet. Não coloque CPF, RG, telefone pessoal, endereço residencial, senhas ou qualquer dado sigiloso na página.

## Parte 1 — Clonar o repositório de exemplo

Você vai trabalhar sobre uma cópia local do projeto [perfil_prof](https://github.com/ffelipeao/perfil_prof), um site estático feito só com HTML, CSS e JavaScript.

1. Crie uma pasta para as aulas, por exemplo `Documentos/nuvem`.
2. Abra o terminal nessa pasta (no Windows: clique com o botão direito na pasta → **Abrir no Terminal**, ou use o Git Bash).
3. Execute o comando abaixo. Use o endereço **HTTPS**, que não exige chave SSH:

```bash
git clone https://github.com/ffelipeao/perfil_prof.git
cd perfil_prof
```

4. Confira se a pasta tem esta estrutura:

```text
perfil_prof/
├── index.html      ← página principal (é ela que você vai editar)
├── css/
│   └── estilo.css  ← cores, fontes e layout
├── js/
│   └── script.js   ← comportamentos da página
├── imagens/        ← coloque aqui a sua foto
└── README.md
```

**Sem Git instalado?** Na página do repositório, clique em **Code → Download ZIP**, extraia o arquivo e renomeie a pasta `perfil_prof-main` para `perfil_prof`.

> Guarde bem essa estrutura: o S3 vai precisar recebê-la **exatamente igual**, com o `index.html` na raiz.

## Parte 2 — Personalizar o index.html no VS Code

Todos os dados pessoais ficam no arquivo `index.html`; você só precisa trocar textos, links e a foto, sem mexer na estrutura das tags.

**2.1 Abrir o projeto**

1. Abra o VS Code → **File → Open Folder…** (Arquivo → Abrir Pasta) → selecione a pasta `perfil_prof`.
   - Atalho pelo terminal, já dentro da pasta: `code .`
2. No painel **Explorer** (à esquerda), clique em `index.html`.
3. Use **Ctrl+F** (Mac: Cmd+F) para achar cada trecho da tabela abaixo. Com **Ctrl+H** você pode substituir um texto em todas as ocorrências de uma vez (útil para o nome, que aparece em vários lugares).

**2.2 O que alterar**

| Onde (procure por) | O que trocar |
| --- | --- |
| `<title>` e `<meta name="description"` (topo do arquivo) | Título da aba do navegador e descrição do site |
| `<img class="foto-perfil" src=...` | Caminho da sua foto (veja 2.3) e o texto do `alt` |
| `<p class="destaque">` | Frase curta de áreas de interesse |
| `<h1>` | Seu nome completo |
| `<p class="resumo-hero">` | Sua apresentação em uma ou duas frases |
| `<div class="credenciais">` | Siglas de títulos ou certificações (ou apague os `<span>` que não usar) |
| `href="https://www.linkedin.com/in/..."` | Link do seu LinkedIn (aparece mais de uma vez) |
| Menu: `aria-label="Voltar ao início">FAO` | Suas iniciais |
| Seção `id="sobre"` | Sua formação e resumo; cidade em `<div class="localizacao">` |
| Seção `id="experiencia"` | Cada `<article class="trajetoria">` é um item: tipo, instituição (`<h3>`), cargo/curso e descrição |
| Seção `id="especialidades"` | Quatro blocos `<article class="especialidade">` com suas competências |
| Seção `id="certificacoes"` e `<details class="cursos">` | Suas certificações e cursos (ajuste também o contador `<small>11 formações</small>`) |
| Seção `id="pesquisa"` | Um projeto seu em destaque (ou apague a seção inteira, de `<section` até `</section>`) |
| Seção `id="contato"` | Links de LinkedIn, Lattes, ORCID, GitHub e Instagram — apague os `<a>` que você não tiver |
| `<footer class="rodape">` | Seu nome e cidade |

**Dica de ouro:** para remover um item (uma experiência, uma certificação), apague o bloco inteiro do `<article ...>` até o `</article>` correspondente. Apagar só metade quebra o layout.

**2.3 Trocar a foto**

1. Copie sua foto para a pasta `imagens/` com um nome simples, **sem espaços nem acentos e em minúsculas**, por exemplo `imagens/foto.jpg`.
2. No `index.html`, troque todo o valor do `src` da imagem por:

```html
<img
  class="foto-perfil"
  src="imagens/foto.jpg"
  alt="Foto de Seu Nome"
  width="240"
  height="240"
/>
```

> O S3 diferencia maiúsculas de minúsculas: `Foto.JPG` e `foto.jpg` são arquivos diferentes. Um erro aqui faz a foto sumir depois de publicada, mesmo aparecendo no seu computador (o Windows não diferencia).

**2.4 Testar no seu computador**

1. Salve o arquivo (**Ctrl+S**).
2. Abra o `index.html` com duplo clique na pasta, ou instale a extensão **Live Server** no VS Code e clique em **Go Live** (canto inferior direito).
3. Confira nome, textos, foto e clique em todos os links. Só avance quando a página estiver do jeito que você quer.

## Parte 3 — Entrar no AWS Console (plano gratuito)

Contas novas criadas desde 15/07/2025 escolhem entre o **plano gratuito** e o **plano pago**; escolha o **gratuito**, que não gera cobrança. Ele dá US$ 100 em créditos na criação (e até mais US$ 100 completando atividades guiadas no console) e dura até 6 meses ou até os créditos acabarem, o que vier primeiro ([documentação AWS](https://docs.aws.amazon.com/pt_br/awsaccountbilling/latest/aboutv2/free-tier-plans.html)).

**3.1 Já tenho conta**

1. Acesse [console.aws.amazon.com](https://console.aws.amazon.com/).
2. Escolha **Usuário raiz (Root user)**, informe o e-mail da conta e a senha.
3. Pule para o passo 3.3.

**3.2 Ainda não tenho conta**

1. Acesse [aws.amazon.com/free](https://aws.amazon.com/free/) e clique em **Criar uma conta gratuita**.
2. Informe e-mail e nome da conta (ex.: `nuvem-seunome`) e confirme o código enviado por e-mail.
3. Crie a senha do usuário raiz — forte e anotada em local seguro.
4. Preencha os dados de contato (tipo **Pessoal**).
5. Na escolha de plano, selecione **Plano gratuito (Free plan)**.
6. Informe o cartão para verificação. Pode aparecer uma cobrança temporária de valor simbólico, que é estornada.
7. Confirme o telefone por SMS ou ligação.
8. No plano de suporte, deixe **Básico (gratuito) (Basic)**.
9. Aguarde a ativação (normalmente minutos; pode levar algumas horas) e faça login como **Usuário raiz (Root user)**.

**3.3 Proteções obrigatórias (5 minutos)**

1. **MFA na conta raiz:** clique no nome da conta (canto superior direito) → **Credenciais de segurança (Security credentials)** → **Atribuir dispositivo MFA (Assign MFA device)** → **Aplicativo autenticador (Authenticator app)** e leia o QR code com Google Authenticator, Microsoft Authenticator ou similar.
2. **Alerta de gastos:** no campo de busca do topo, digite **Orçamentos (Budgets)** → **Criar orçamento (Create budget)** → modelo **Orçamento de gasto zero (Zero spend budget)** → informe seu e-mail. Você será avisado se algo sair do gratuito.
3. **Região:** no canto superior direito, selecione **Leste dos EUA (Norte da Virgínia) us-east-1**. Use sempre a mesma região durante a aula. Motivo: Virgínia e Ohio (us-east-2) têm o mesmo preço de S3, o menor da AWS, enquanto São Paulo custa bem mais em armazenamento e transferência. Para um site pessoal, a distância até os EUA não faz diferença perceptível. Quem preferir pode usar Ohio: os passos são idênticos.

> Nunca compartilhe a senha, o código MFA ou chaves de acesso — nem com colegas, nem em prints enviados ao grupo da turma.

## Parte 4 — Criar o bucket S3

O nome do bucket precisa ser único no mundo inteiro (entre todas as contas AWS) e vai aparecer no endereço do seu site.

**Regras do nome:** de 3 a 63 caracteres, só letras minúsculas, números, hífens e pontos; sem espaços, acentos, `_` ou maiúsculas; começa e termina com letra ou número.

**Padrão sugerido para a turma:** `perfil-nome-sobrenome-2026` — ex.: `perfil-maria-souza-2026`. Se já existir, acrescente sua matrícula ou um número.

1. No console, busque **S3** no campo de pesquisa do topo e abra o serviço.
2. Confirme a região **us-east-1 (Norte da Virgínia)** no canto superior direito.
3. Clique em **Criar bucket (Create bucket)**.
4. **Tipo de bucket (Bucket type):** **Uso geral (General purpose)**.
5. **Nome do bucket (Bucket name):** o nome escolhido.
6. **Propriedade de objeto (Object Ownership):** mantenha **ACLs desabilitadas (recomendado) (ACLs disabled — recommended)**.
7. **Configurações de bloqueio do acesso público para este bucket (Block Public Access settings for this bucket):** por enquanto, deixe como está (marcado). Vamos liberar na Parte 5, entendendo o motivo.
8. **Versionamento de bucket (Bucket Versioning):** **Desabilitar (Disable)**. Em **Criptografia padrão (Default encryption)**, mantenha o padrão (SSE-S3).
9. Clique em **Criar bucket (Create bucket)** no fim da página.

O bucket aparece na lista **Buckets de uso geral (General purpose buckets)**. Clique no nome dele para abrir.

## Parte 5 — Configurar o bucket como site estático

São três ajustes, e o site só funciona com os três: ativar a hospedagem, liberar o bloqueio de acesso público e criar uma política que permita **somente leitura** para qualquer visitante.

**5.1 Ativar a hospedagem de site estático**

1. Dentro do bucket, abra a aba **Propriedades (Properties)**.
2. Role até o fim, em **Hospedagem de site estático (Static website hosting)**, e clique em **Editar (Edit)**.
3. Marque **Habilitar (Enable)**.
4. **Tipo de hospedagem (Hosting type):** **Hospedar um site estático (Host a static website)**.
5. **Documento de índice (Index document):** `index.html` (exatamente assim, em minúsculas).
6. **Documento de erro (Error document):** `index.html` também (opcional; evita a página de erro padrão da AWS).
7. Clique em **Salvar alterações (Save changes)**.
8. Volte ao fim da aba **Propriedades (Properties)** e copie o **Endpoint do site do bucket (Bucket website endpoint)**. Em Virgínia ele terá o formato abaixo (em Ohio muda um pouco: s3-website.us-east-2); copie sempre o que o console mostrar:

```text
http://perfil-maria-souza-2026.s3-website-us-east-1.amazonaws.com
```

**5.2 Liberar o acesso público**

Por segurança, todo bucket nasce bloqueado. Um site precisa ser lido por qualquer pessoa, então vamos desligar esse bloqueio **apenas neste bucket**.

1. Abra a aba **Permissões (Permissions)**.
2. Em **Bloquear acesso público (configurações do bucket) (Block public access — bucket settings)**, clique em **Editar (Edit)**.
3. Desmarque **Bloquear todo o acesso público (Block all public access)**; todas as quatro opções ficam desmarcadas.
4. Clique em **Salvar alterações (Save changes)**, digite `confirm` na caixa e confirme.

**5.3 Criar a política do bucket (bucket policy)**

1. Ainda em **Permissões (Permissions)**, em **Política do bucket (Bucket policy)**, clique em **Editar (Edit)**.
2. Cole o JSON abaixo, **trocando `NOME-DO-SEU-BUCKET` pelo nome real** (mantenha o `/*` no final):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "LeituraPublicaDoSite",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::NOME-DO-SEU-BUCKET/*"
    }
  ]
}
```

3. Clique em **Salvar alterações (Save changes)**. Se aparecer erro de política, confira o nome do bucket, as aspas e as vírgulas.
4. O bucket passa a exibir o selo **Acessível publicamente (Publicly accessible)**. Isso é esperado para um site.

**Por que é seguro:** a política libera apenas `s3:GetObject`, ou seja, **ler** arquivos. Ninguém de fora consegue enviar, alterar ou apagar nada no bucket.

## Parte 6 — Enviar os arquivos para a raiz do bucket

Envie o **conteúdo** da pasta `perfil_prof`, e não a pasta em si: o `index.html` tem que ficar na raiz do bucket, ao lado das pastas `css/`, `js/` e `imagens/`.

| Resultado no bucket | Situação |
| --- | --- |
| `index.html`, `css/`, `js/`, `imagens/` na raiz | **Correto** — o site abre |
| `perfil_prof/index.html` (tudo dentro de uma pasta) | **Errado** — o endereço dá erro 404 |
| Só `index.html`, sem as pastas | **Errado** — a página abre sem cores e sem foto |

**6.1 Enviar pelo console**

1. Abra a aba **Objetos (Objects)** do bucket e clique em **Fazer upload (Upload)**.
2. No seu computador, **entre** na pasta `perfil_prof` e selecione: `index.html`, `css`, `js` e `imagens`.
3. Arraste os quatro itens juntos para a área tracejada da página de upload. Como alternativa, use **Adicionar arquivos (Add files)** para o `index.html` e **Adicionar pasta (Add folder)** para cada pasta.
4. **Não envie:** a pasta oculta `.git`, o `README.md` (opcional) nem arquivos de rascunho.
5. Confira a lista em **Arquivos e pastas (Files and folders)**. Os caminhos devem aparecer como `index.html`, `css/estilo.css`, `js/script.js`, `imagens/foto.jpg` — **sem** `perfil_prof/` na frente.
6. Clique em **Fazer upload (Upload)** e aguarde **Upload bem-sucedido (Upload succeeded)**. Clique em **Fechar (Close)**.

**6.2 Conferir**

Na aba **Objetos (Objects)** você deve ver exatamente isto na raiz:

```text
css/
imagens/
js/
index.html
```

O console define o tipo de cada arquivo (`text/html`, `text/css`, `image/jpeg`) automaticamente, por isso o upload pelo navegador é o caminho recomendado para a aula.

**Enviou errado?** Selecione os objetos na aba **Objetos (Objects)** → **Excluir (Delete)** → digite a frase de confirmação solicitada pela console (em inglês, `permanently delete`) e repita o upload.

**Atualizar o site depois:** edite no VS Code e envie de novo apenas o arquivo alterado, no mesmo caminho. O S3 substitui a versão anterior. Se o navegador mostrar a versão antiga, recarregue com **Ctrl+F5**.

## Parte 7 — Testar e corrigir erros

Abra o **Endpoint do site do bucket (Bucket website endpoint)** em **Propriedades (Properties) → Hospedagem de site estático (Static website hosting)** numa aba anônima do navegador; se o seu perfil aparecer completo, a publicação está pronta.

**Use o endereço certo.** O endpoint de site tem `s3-website` no meio e começa com `http://`. A **URL do objeto (Object URL)** de um arquivo (`https://...s3.us-east-1.amazonaws.com/index.html`) não é o endereço do site.

| Sintoma | Causa provável | Como resolver |
| --- | --- | --- |
| **403 Forbidden / AccessDenied** | Bloqueio público ainda ativo ou política ausente/errada | Refaça 5.2 e 5.3; confira o nome do bucket e o `/*` no `Resource` |
| **404 Not Found / NoSuchKey** | `index.html` dentro de uma subpasta, ou documento de índice digitado diferente | Confira a raiz do bucket (Parte 6) e o campo **Documento de índice (Index document)** (5.1) |
| **404 NoSuchWebsiteConfiguration** | Hospedagem estática não foi ativada | Refaça 5.1 |
| Página sem cores ou sem layout | Pasta `css/` não enviada ou enviada em outro nível | Envie `css/estilo.css` na raiz, mantendo o nome da pasta |
| Foto não aparece | Nome diferente no `src` (maiúsculas, espaço, extensão) ou foto fora de `imagens/` | Deixe o nome do arquivo e o `src` idênticos, tudo em minúsculas |
| O navegador baixa o arquivo em vez de abrir | Tipo do arquivo errado (comum ao enviar por outras ferramentas) | Reenvie pelo console do S3 |
| Alteração não aparece | Cache do navegador | Recarregue com **Ctrl+F5** ou use aba anônima |
| "Não seguro" na barra de endereço | O endpoint de site do S3 funciona só em HTTP | Normal nesta aula; HTTPS é tratado no Extra |

**Entrega:** envie ao professor o link do endpoint do seu site no canal indicado pela turma.

## Extra (opcional) — Domínio próprio e site permanente

> **Esta parte NÃO é obrigatória.** Faça apenas se quiser manter um site com o seu nome no ar depois da disciplina. Ela envolve **custos pagos por você**: o registro do domínio e, depois do período gratuito, o uso da AWS.

**Quanto custa**

| Item | Custo | Observação |
| --- | --- | --- |
| Domínio `.com.br` no [Registro.br](https://registro.br/dominio/) | R$ 40,00 por ano | Pago por boleto, Pix ou cartão; pode pagar até 10 anos de uma vez; exige CPF |
| AWS depois do plano gratuito | Poucos centavos de dólar por mês para um site pequeno | O plano gratuito termina em até 6 meses (ou ao fim dos créditos) e a **conta é encerrada** se não houver upgrade — o site sai do ar |

**E.1 Manter a conta e o site ativos**

1. Antes do fim do plano gratuito, no console, clique em **Fazer upgrade do plano (Upgrade plan)** ou acesse **Faturamento e gerenciamento de custos (Billing and Cost Management)** e mude para o **plano pago**. Créditos restantes continuam valendo.
2. Mantenha o **Orçamento de gasto zero (Zero spend budget)** ou crie um orçamento de US$ 1–5 para ser avisado de qualquer cobrança.

**E.2 Registrar o domínio no Registro.br**

1. Acesse [registro.br](https://registro.br/) e pesquise o nome desejado (ex.: `mariasouza.com.br`).
2. Se aparecer **Domínio disponível para registro**, clique em **Registrar**.
3. Faça login ou crie sua conta (ID Registro.br) com CPF e ative a verificação em duas etapas.
4. Na configuração de DNS, escolha **Utilizar os DNS do Registro.br**.
5. Conclua o pagamento. O domínio só é ativado após a confirmação.

**E.3 Criar o bucket com o nome do domínio**

Para usar domínio próprio, o nome do bucket precisa ser **idêntico** ao endereço que o visitante digita.

1. Crie um novo bucket chamado `www.mariasouza.com.br` (com o seu domínio), na mesma região.
2. Repita as Partes 5 e 6 nele (hospedagem, acesso público, política com o novo nome, upload).
3. Copie o novo **Endpoint do site do bucket (Bucket website endpoint)**.

**E.4 Apontar o domínio para o bucket**

1. No Registro.br, abra o domínio → **DNS** → **Editar zona**.
2. Adicione um registro: **Tipo** `CNAME`, **Nome** `www`, **Dados** = o endpoint **sem** `http://` (ex.: `www.mariasouza.com.br.s3-website-us-east-1.amazonaws.com`).
3. Salve. A propagação pode levar de minutos a algumas horas.
4. Teste em `http://www.mariasouza.com.br`.

**Limitações desta configuração simples**

- Funciona no endereço com `www`. O domínio "puro" (`mariasouza.com.br`) não aceita CNAME; para ele, o caminho profissional é usar o **Amazon Route 53** (cerca de US$ 0,50/mês por zona).
- O site fica só em **HTTP**. Para ter o cadeado **HTTPS**, o próximo passo é colocar o **Amazon CloudFront** na frente do bucket com um certificado gratuito do **AWS Certificate Manager** — assunto para uma aula futura ou uma pesquisa mais detalhada sobre este tema (um desafio para você superar).

## Checklist e limpeza

**Antes de entregar**

- [ ] Dados pessoais trocados no `index.html` (nome, resumo, trajetória, certificações, links)
- [ ] Foto em `imagens/` com nome em minúsculas e `src` correspondente
- [ ] MFA ativo e **Orçamento de gasto zero (Zero spend budget)** criado
- [ ] Bucket com nome único, em us-east-1 (ou us-east-2)
- [ ] **Hospedagem de site estático (Static website hosting)** ativada com `index.html`
- [ ] Bloqueio público desligado e **política do bucket (bucket policy)** com `s3:GetObject`
- [ ] `index.html`, `css/`, `js/` e `imagens/` na raiz do bucket
- [ ] Site abrindo pelo endpoint em aba anônima, com cores e foto
- [ ] Link do endpoint enviado ao professor

**Não vai manter o site depois da disciplina?** Remova tudo para não deixar recursos esquecidos:

1. S3 → selecione o bucket → **Esvaziar (Empty)** → digite a frase de confirmação solicitada (em inglês, `permanently delete`) → confirme.
2. Selecione o bucket de novo → **Excluir (Delete)** → digite o nome do bucket → confirme.

## Fontes

- [Repositório perfil_prof](https://github.com/ffelipeao/perfil_prof)
- [AWS — Escolhendo um plano do Nível gratuito](https://docs.aws.amazon.com/pt_br/awsaccountbilling/latest/aboutv2/free-tier-plans.html)
- [AWS — Explore serviços com o Nível gratuito](https://docs.aws.amazon.com/pt_br/awsaccountbilling/latest/aboutv2/free-tier.html)
- [AWS — Hospedagem de site estático no Amazon S3](https://docs.aws.amazon.com/pt_br/AmazonS3/latest/userguide/WebsiteHosting.html)
- [Registro.br — Sobre domínios e preços](https://registro.br/dominio/)
- [Registro.br — Pagamento de domínio](https://registro.br/ajuda/pagamento-de-dominio/)
