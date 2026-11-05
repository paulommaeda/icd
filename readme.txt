=== Instituto Caminho de Davi - Gestão de Visitas ===
Contributors: icd
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.10.0

Sistema mobile first para acompanhamento das visitas pedagógicas do Instituto Caminho de Davi.

== Modelo operacional ==
* Mediadores ficam vinculados às escolas e possuem agenda por dia/turno.
* Pedagogas realizam visitas de supervisão às escolas.
* Cada visita registra pedagoga(s), mediadores acompanhados e observações.
* O dashboard possui filtros por período, município, escola, mediador, pedagoga, status e turno.

== Portal ICD ==
* Com o plugin ativo, a página inicial e as rotas públicas do WordPress exibem o portal do ICD. O portal carrega seu próprio layout, sem o cabeçalho, menu, conteúdo ou rodapé do tema.
* Use Gestão ICD no dashboard para acessar as áreas autorizadas para seu perfil. A gestão de visitas, cadastros, vínculos, agenda, alunos e importação XLSX funciona no portal.
* Administradores podem criar contas e atribuir perfis do sistema em Gestão ICD > Acessos. A administração do WordPress fica disponível somente para administradores do WordPress.
* Os endereços anteriores do portal e páginas com os shortcodes continuam encaminhando para as telas correspondentes. O portal funciona também com links permanentes simples.

== Shortcodes ==
[icd_dashboard]
[icd_nova_visita]
[icd_agenda]
[icd_minhas_visitas]
[icd_perfil]
[icd_relatorios]
[icd_alunos]

== Alunos e equipe ==
* Em Gestão ICD > Mediadores, selecione a pedagoga responsável na lista e clique em Salvar vínculo. Na primeira migração, atribua as pedagogas às mediadoras já cadastradas. As pedagogas que realizaram visitas anteriores continuam registradas no histórico de cada visita.
* Em Gestão ICD > Alunos, cadastre o nome e a data de nascimento e selecione a escola e a mediadora. Use Trocar vínculos na ficha para transferir o aluno.
* Vincule as pedagogas e mediadoras aos respectivos usuários WordPress em seus cadastros. Use os papéis Pedagoga ICD e Mediador ICD para esses acessos.
* No portal, a aba Alunos mostra os alunos da mediadora e os alunos da equipe da pedagoga. Cada ficha permite registrar observações datadas e baixar o histórico em PDF. Os vínculos atuais definem quem pode acessar o histórico.
* Atribua o papel Direção ICD em Gestão ICD > Acessos para dar acesso de consulta a todos os alunos, pedagogas, mediadoras e relatórios. Administradores e coordenadores podem cadastrar e transferir alunos e mediadoras.

== Instalação e atualizações ==
* Para instalar ou atualizar manualmente, envie o ZIP com a pasta icd-visitas em Plugins > Adicionar plugin > Enviar plugin. Em uma atualização, escolha substituir a versão atual quando o WordPress perguntar. As tabelas e os perfis são atualizados ao carregar a nova versão.
* Em Plugins > Plugins instalados, use Ativar atualizações automáticas para o ICD Visitas. O WordPress consulta o repositório público paulommaeda/icd no GitHub e usa seu fluxo padrão de atualização.
* Para distribuir uma nova versão, aumente a versão no cabeçalho e na constante ICD_VISITAS_VERSION do arquivo icd-visitas.php, atualize este arquivo e publique a versão testada na branch main. O atualizador usa o ZIP do mesmo commit do cabeçalho que anunciou a versão.

== 0.10.0 ==
* Portal independente do tema com layout próprio, login, navegação e manifesto de aplicativo.
* Gestão de visitas, cadastros, vínculos, alunos e importação no front-end.
* Área de acessos para administradores criarem contas e atribuírem perfis ICD no portal.
* Bloqueio do painel e das rotas genéricas da API do WordPress para usuários que não são administradores.

== 0.9.0 ==
* Vínculo de supervisão entre mediadoras e pedagogas com troca direta na lista de mediadoras.
* Cadastro de alunos com escola e mediadora, troca de vínculos pela ficha e acesso pelo portal.
* Histórico de observações por aluno e relatório individual em PDF.
* Papel Direção ICD com acesso de consulta à equipe, aos alunos e aos relatórios.
* Atualizações pelo GitHub integradas ao painel do WordPress.

== 0.3.0 ==
* Cadastro de pedagogas e vínculo com usuário WordPress.
* Novo papel Pedagoga ICD.
* Separação entre pedagoga visitante e mediadores acompanhados.
* Filtros completos no dashboard do front-end.
* Histórico da pedagoga no PWA.
* Observações destacadas no registro e histórico das visitas.
* Compatibilidade preservada com o histórico importado na 0.2.0.


== 0.5.0 ==
* Agenda de visitas no front-end com próximos 7 dias.
* Cadastro e edição de visitas agendadas no front-end.
* Botão Visita realizada sem duplicação de registro.
* Edição e exclusão de visitas no histórico.
* Remoção dos campos de horário.
* Filtros do dashboard recolhem automaticamente após aplicação.


== 0.5.0 ==
* Editar e excluir visitas pelo admin.
* Escolas clicáveis no dashboard administrativo.
* Modal com calendário mensal e dias com visita destacados em verde.
* Detalhes das visitas por dia e atalho para edição.


== 0.6.1 ==
* Login do PWA por Passkeys/WebAuthn (Face ID, impressão digital ou PIN do dispositivo).
* Tela de login própria no front-end, sem obrigar passagem pelo wp-login.php.
* Usuário e senha permanecem como alternativa de recuperação.
* Cadastro e remoção de dispositivos diretamente no Dashboard.
* A biometria nunca é enviada ao WordPress; o banco armazena apenas a chave pública da Passkey.
* Validação criptográfica de desafio, origem, RP ID, user verification e assinatura da autenticação.
* Proteção simples contra excesso de tentativas de login por senha.
* Requer HTTPS para uso de Passkeys.


== 0.6.2 ==
* Logout de usuários do ICD redireciona para /icd-acesso/ em vez de /wp-login.php.
* Primeiro login com senha abre onboarding automático para ativar Passkey.
* Nome do dispositivo é detectado automaticamente e não é editável pelo usuário.
* Removidos campos manuais de nome do dispositivo/senha do bloco de segurança do dashboard.


== 0.6.4 ==
* Agenda: botão "Visita realizada" agora abre um modal antes da conclusão.
* O modal sugere o preenchimento das observações, reaproveita observações já existentes e salva tudo ao confirmar.
* A visita programada é convertida para realizada no mesmo registro, sem duplicação.

== 0.6.3 ==
* Corrigido o onboarding de Passkey após login por senha.
* Perfil completo no front-end com nome, e-mail e telefone.
* Alteração de senha no front-end.
* Recuperação/redefinição de senha no acesso próprio do PWA, sem wp-login.php.
* Barra de ferramentas do WordPress oculta para usuários que não são administradores.
* Botão Sair do sistema com logout direto para /icd-acesso/.
* Configuração de biometria removida do Dashboard e movida para o Perfil.
* Perfil incluído na navegação inferior do PWA.


== 0.7.0 ==
* Nova página frontal de relatórios de visitas.
* Filtro por período, pedagoga, escola e mediador.
* Geração direta de PDF com logo, dados das visitas, observações e paginação X de XX.


== 0.7.1 ==
* Removido o botão redundante “+ Nova” do cabeçalho do Dashboard; o registro de visita permanece na navegação inferior.
* Reorganizado o acesso a Relatórios no front-end, agora em uma ação própria abaixo do cabeçalho.
* Layout mobile do cabeçalho simplificado para evitar sobreposição entre título e botões.

== 0.7.3 ==
* Relatórios em PDF passam a ser gerados no front-end sem sair da tela.
* Adicionadas ações móveis para Compartilhar / Salvar, Baixar PDF e Visualizar.
* Compartilhamento usa o menu nativo do aparelho quando disponível.


== 0.7.3 ==
* Geração de PDF via AJAX mais robusta, com erros legíveis no front-end.
* Loading global com blur e bloqueio de cliques durante navegação, formulários e requisições ao servidor.


== 0.8.0 ==
* Novo importador inteligente de XLSX em etapas no admin.
* Upload e detecção automática da linha de cabeçalho e das colunas.
* Escolha do tipo de carga: Pedagogas, Mediadores, Escolas ou Visitas.
* Mapeamento livre entre colunas do Excel e campos do sistema, com sugestões automáticas.
* Prévia e validação antes da confirmação da importação.
* Tratamento de duplicados com opção de atualizar ou ignorar.
* Importação de relacionamentos de visitas por nome de escola, pedagoga e mediador.
* Opção de criar automaticamente registros relacionados ausentes durante cargas de visitas.
