# Guia do Utilizador do CreatorStack AI

[English](user-guide-en.md) | Português | [Español](user-guide-es.md)

O CreatorStack AI ajuda a transformar vídeos do YouTube e ficheiros de áudio enviados para a Biblioteca de Multimédia em rascunhos de artigos no WordPress. Quando o fornecedor de IA suporta conversão de texto para voz, também pode criar uma versão áudio de um artigo.

## Antes De Começar

Precisa de:

- Um site WordPress com as APIs do WordPress AI Client disponíveis. Este plugin espera atualmente WordPress 7.0 beta ou mais recente.
- Um fornecedor de IA configurado no WordPress. Use **Definições > Connectors** quando esse ecrã estiver disponível.
- Uma chave da YouTube Data API v3 e o ID do canal do YouTube para os fluxos baseados em vídeos.
- Um cliente OAuth da Google do tipo **Web application** se quiser que o CreatorStack AI leia legendas através da API oficial YouTube Captions.
- Uma conta WordPress com as permissões adequadas:
  - Administradores podem configurar as definições do plugin.
  - Autores, Editores e Administradores podem gerar e editar rascunhos quando têm `edit_posts`.

Para os fluxos de áudio, o fornecedor de IA configurado tem de suportar a capacidade necessária:

- **YouTube to Post** requer geração de texto.
- **Audio to Post** requer entrada de áudio e geração de texto.
- **Post to Audio** requer conversão de texto para voz.

## Configurar O Fornecedor De IA

1. No painel de administração do WordPress, abra **Definições > Connectors**.
2. Instale ou ative um conector de fornecedor de IA.
3. Adicione as credenciais exigidas por esse conector.
4. Volte a **Definições > AI Content Suite**.
5. Na secção **AI Provider**, clique em **Test AI Connection**.
6. Confirme que o teste é concluído com sucesso antes de gerar conteúdo.

Se o WordPress não mostrar **Definições > Connectors**, use o ecrã de definições do AI Client indicado na secção **AI Provider**.

## Configurar O YouTube

1. Abra **Definições > AI Content Suite**.
2. Em **YouTube Integration**, siga o assistente de configuração.
3. Ative a **YouTube Data API v3** na Google Cloud.
4. Crie uma chave da YouTube Data API e cole-a em **YouTube API Key**.
5. Encontre o ID do seu canal do YouTube e cole-o em **YouTube Channel ID**.
6. Crie um cliente OAuth da Google com **Web application** como tipo de aplicação.
7. Copie o **Authorized redirect URI** mostrado pelo WordPress e adicione-o ao cliente OAuth na Google Cloud.
8. Cole o conteúdo do `client_secret.json` no assistente, clique em **Fill OAuth fields** e depois em **Save Changes**.
9. Depois de a página recarregar, clique em **Connect YouTube** e conclua o fluxo de consentimento da Google.

O OAuth é usado para descarregar legendas pela via oficial. A conta do YouTube ligada tem de conseguir editar os vídeos cujas legendas pretende usar.

## Definir As Predefinições De Conteúdo

Em **Definições > AI Content Suite > Content Settings**, escolha:

- **Default Output Language**: a língua usada por predefinição, salvo se a alterar durante a geração.
- **Post Length**:
  - Short: cerca de 600 a 900 palavras.
  - Medium: cerca de 1.000 a 1.500 palavras.
  - Long: cerca de 1.800 a 2.500 palavras.
- **Writing Persona**: orientação opcional sobre tom, público, estrutura ou estilo.

Em **AI Provider**, também pode escolher um **Preferred AI Model**. Mantenha **Automatic (recommended)** salvo se precisar de um modelo específico. Se o modelo preferido não estiver disponível, o AI Client pode usar outro modelo compatível configurado.

## Criar Um Rascunho A Partir De Um Vídeo Do YouTube

Pode começar no widget do painel ou na página completa de vídeos.

A partir do painel:

1. Abra **Dashboard**.
2. Encontre o widget **YouTube to Blog**.
3. Clique em **Generate Post** num vídeo recente.

A partir da página completa:

1. Abra **Tube-to-Blog > YouTube Content**.
2. Navegue pelos vídeos do canal.
3. Clique em **Generate Post** no vídeo que pretende usar.
4. Use **Load More Videos** se precisar de vídeos mais antigos.

Quando o modal de geração abrir:

1. Escolha a língua de saída.
2. Ajuste a persona de escrita, se necessário.
3. Se as legendas do YouTube estiverem em falta ou não forem fiáveis, ative **Use a custom transcript instead of fetching captions** e cole pelo menos 50 caracteres de transcrição.
4. Clique em **Generate**.
5. Reveja o **Draft Preview**.
6. Clique em **Regenerate** se quiser uma nova versão.
7. Clique em **Save as Draft** quando a pré-visualização estiver pronta.
8. Abra **Edit Draft** para rever, editar e publicar o artigo.

Os rascunhos criados a partir do YouTube incluem o artigo gerado, uma incorporação do vídeo, metadados de origem e a miniatura do YouTube como imagem de destaque quando o WordPress a consegue descarregar.

## Criar Um Rascunho A Partir De Um Ficheiro De Áudio

1. Abra **Tube-to-Blog > Audio to Post**.
2. Clique em **Create Draft From Audio**. Isto abre um novo rascunho.
3. Na barra lateral do editor, abra o painel **AI Content Suite**.
4. Em **Audio to Post**, clique em **Select Audio**.
5. Escolha um ficheiro de áudio da Biblioteca de Multimédia ou envie um novo.
6. Selecione a língua de saída.
7. Ajuste a persona de escrita, se necessário.
8. Clique em **Generate Draft**.

O CreatorStack AI atualiza o rascunho atual com um título e conteúdo gerados, e depois guarda o rascunho.

As extensões de áudio suportadas são `mp3`, `m4a`, `wav`, `ogg`, `webm`, `flac` e `aac`. O tamanho máximo é 25 MB ou o limite de envio do site, consoante o que for menor.

## Gerar Áudio A Partir De Um Artigo

1. Abra um artigo ou rascunho existente.
2. Na barra lateral do editor, abra o painel **AI Content Suite**.
3. Em **Post to Audio**, introduza um nome de voz se o fornecedor suportar seleção de voz.
4. Clique em **Generate Audio**.

Se o artigo tiver alterações por guardar, o CreatorStack AI guarda-as primeiro. Depois cria um anexo de áudio, insere um bloco de Áudio no topo do artigo, substitui qualquer bloco de áudio anterior do CreatorStack AI, guarda o ID do anexo nos metadados do artigo e volta a guardar o artigo.

## Rever A Utilização De IA

Administradores podem abrir **Definições > AI Content Suite > AI Usage** para ver gerações recentes. A tabela mostra data, origem, estado, fornecedor, modelo e uso de tokens quando o fornecedor comunica essa informação.

## Resolução De Problemas

- **IA indisponível**: configure um fornecedor de IA e execute **Test AI Connection**.
- **Nenhuma legenda encontrada**: ligue o OAuth do YouTube, escolha um vídeo com legendas ou use uma transcrição manual.
- **Transcrição manual demasiado curta**: cole pelo menos 50 caracteres.
- **Já existe um artigo a ser gerado**: aguarde até a geração atual terminar. O plugin impede gerações concorrentes por utilizador.
- **Aviso sobre imagem de destaque**: o rascunho foi criado, mas o WordPress não conseguiu descarregar a miniatura do YouTube.
- **Ficheiro de áudio rejeitado**: verifique a extensão, o tipo MIME e o tamanho do ficheiro.
- **Problemas em localhost**: localhost é suportado para desenvolvimento, mas o WordPress precisa de acesso HTTPS de saída para o YouTube e para o fornecedor de IA configurado.
