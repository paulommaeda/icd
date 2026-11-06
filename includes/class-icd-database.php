<?php
if (!defined('ABSPATH')) exit;
class ICD_Visitas_Database {
    const SCHEMA_VERSION = '0.11.0';

    public static function table($name){ global $wpdb; return $wpdb->prefix.'icd_visitas_'.$name; }
    public static function old_table($name){ global $wpdb; return $wpdb->prefix.'jv_'.$name; }
    public static function exists($table){ global $wpdb; return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) === $table; }
    public static function install(){
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate();
        $sql=[];
        $sql[]="CREATE TABLE ".self::table('municipios')." (id bigint unsigned NOT NULL AUTO_INCREMENT,nome varchar(150) NOT NULL,uf char(2) NOT NULL DEFAULT 'PR',ativo tinyint(1) NOT NULL DEFAULT 1,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NULL,PRIMARY KEY(id),KEY nome(nome)) $c;";
        $sql[]="CREATE TABLE ".self::table('escolas')." (id bigint unsigned NOT NULL AUTO_INCREMENT,municipio_id bigint unsigned NULL,nome varchar(190) NOT NULL,endereco varchar(190) NULL,numero varchar(30) NULL,bairro varchar(120) NULL,cep varchar(20) NULL,telefone varchar(40) NULL,email varchar(190) NULL,meta_visitas_semana decimal(4,1) NULL,observacoes text NULL,ativo tinyint(1) NOT NULL DEFAULT 1,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NULL,PRIMARY KEY(id),KEY municipio_id(municipio_id),KEY nome(nome)) $c;";
        $sql[]="CREATE TABLE ".self::table('responsaveis')." (id bigint unsigned NOT NULL AUTO_INCREMENT,escola_id bigint unsigned NOT NULL,nome varchar(190) NOT NULL,cargo varchar(120) NULL,telefone varchar(40) NULL,whatsapp varchar(40) NULL,email varchar(190) NULL,principal tinyint(1) NOT NULL DEFAULT 0,ativo tinyint(1) NOT NULL DEFAULT 1,observacoes text NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NULL,PRIMARY KEY(id),KEY escola_id(escola_id)) $c;";
        $sql[]="CREATE TABLE ".self::table('pedagogas')." (id bigint unsigned NOT NULL AUTO_INCREMENT,wp_user_id bigint unsigned NULL,nome varchar(190) NOT NULL,email varchar(190) NULL,telefone varchar(40) NULL,observacoes text NULL,ativo tinyint(1) NOT NULL DEFAULT 1,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NULL,PRIMARY KEY(id),KEY wp_user_id(wp_user_id),KEY nome(nome)) $c;";
        $sql[]="CREATE TABLE ".self::table('visita_pedagogas')." (id bigint unsigned NOT NULL AUTO_INCREMENT,visita_id bigint unsigned NOT NULL,pedagoga_id bigint unsigned NOT NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY visita_pedagoga(visita_id,pedagoga_id),KEY pedagoga_id(pedagoga_id)) $c;";
        $sql[]="CREATE TABLE ".self::table('vinculos')." (id bigint unsigned NOT NULL AUTO_INCREMENT,escola_id bigint unsigned NOT NULL,mediador_id bigint unsigned NOT NULL,data_inicio date NULL,data_fim date NULL,ativo tinyint(1) NOT NULL DEFAULT 1,observacoes text NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY escola_mediador_ativo(escola_id,mediador_id,ativo),KEY mediador_id(mediador_id)) $c;";
        $sql[]="CREATE TABLE ".self::table('agendas')." (id bigint unsigned NOT NULL AUTO_INCREMENT,vinculo_id bigint unsigned NOT NULL,dia_semana tinyint unsigned NOT NULL,turno varchar(20) NOT NULL,hora_inicio time NULL,hora_fim time NULL,ativo tinyint(1) NOT NULL DEFAULT 1,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY agenda_unica(vinculo_id,dia_semana,turno),KEY dia_semana(dia_semana)) $c;";
        $sql[]="CREATE TABLE ".self::table('visitas')." (id bigint unsigned NOT NULL AUTO_INCREMENT,escola_id bigint unsigned NOT NULL,data_visita date NOT NULL,turno varchar(20) NOT NULL DEFAULT 'manha',hora_inicio time NULL,hora_fim time NULL,status varchar(30) NOT NULL DEFAULT 'realizada',tipo varchar(30) NOT NULL DEFAULT 'regular',observacoes text NULL,origem varchar(30) NOT NULL DEFAULT 'manual',chave_importacao varchar(191) NULL,criado_por bigint unsigned NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY chave_importacao(chave_importacao),KEY escola_id(escola_id),KEY data_visita(data_visita),KEY status(status)) $c;";
        $sql[]="CREATE TABLE ".self::table('participantes')." (id bigint unsigned NOT NULL AUTO_INCREMENT,visita_id bigint unsigned NOT NULL,mediador_id bigint unsigned NOT NULL,presente tinyint(1) NOT NULL DEFAULT 1,observacoes text NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY visita_mediador(visita_id,mediador_id),KEY mediador_id(mediador_id)) $c;";
        $sql[]="CREATE TABLE ".self::table('logs')." (id bigint unsigned NOT NULL AUTO_INCREMENT,usuario_id bigint unsigned NULL,entidade varchar(60) NOT NULL,entidade_id bigint unsigned NULL,acao varchar(60) NOT NULL,dados_anteriores longtext NULL,dados_novos longtext NULL,ip varchar(64) NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY entidade(entidade),KEY usuario_id(usuario_id)) $c;";
        $sql[]="CREATE TABLE ".self::table('passkeys')." (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,credential_hash char(64) NOT NULL,credential_id varchar(512) NOT NULL,public_key longtext NOT NULL,algorithm smallint NOT NULL,sign_count bigint unsigned NOT NULL DEFAULT 0,transports varchar(255) NULL,device_name varchar(190) NOT NULL DEFAULT 'Este dispositivo',created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NULL,last_used_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY credential_hash(credential_hash),KEY user_id(user_id)) $c;";
        if (version_compare((string) get_option('icd_visitas_db_version', '0'), '0.8.0', '<')) {
            foreach ($sql as $query) dbDelta($query);
        }

        $mediadores = self::table('mediadores');
        $alunos = self::table('alunos');
        $observacoes = self::table('aluno_observacoes');
        dbDelta("CREATE TABLE $mediadores (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            wp_user_id bigint unsigned NULL,
            nome varchar(190) NOT NULL,
            cargo varchar(100) NOT NULL DEFAULT 'Mediador',
            email varchar(190) NULL,
            telefone varchar(40) NULL,
            observacoes text NULL,
            ativo tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            pedagoga_id bigint unsigned NULL,
            PRIMARY KEY  (id),
            KEY wp_user_id (wp_user_id),
            KEY nome (nome),
            KEY pedagoga_id (pedagoga_id)
        ) $c;");
        dbDelta("CREATE TABLE $alunos (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            nome varchar(190) NOT NULL,
            data_nascimento date NOT NULL,
            escola_id bigint unsigned NOT NULL,
            mediador_id bigint unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            KEY mediador_id (mediador_id),
            KEY escola_id (escola_id),
            KEY nome (nome)
        ) $c;");
        dbDelta("CREATE TABLE $observacoes (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            aluno_id bigint unsigned NOT NULL,
            autor_id bigint unsigned NOT NULL,
            observacao longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            data_registro date NULL,
            visita_id bigint unsigned NULL,
            escola_id bigint unsigned NULL,
            mediador_id bigint unsigned NULL,
            pedagoga_id bigint unsigned NULL,
            PRIMARY KEY  (id),
            KEY aluno_historico (aluno_id,created_at,id),
            KEY visita_id (visita_id)
        ) $c;");

        self::migrate_legacy();
        ICD_Visitas_Permissions::activate();
        $coluna = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $mediadores LIKE %s", $wpdb->esc_like('pedagoga_id')));
        if (!$coluna || !self::exists($alunos) || !self::exists($observacoes)) return;
        $campos = $wpdb->get_col("SHOW COLUMNS FROM $observacoes");
        if (array_diff(['data_registro','visita_id','escola_id','mediador_id','pedagoga_id'], $campos)) return;
        if ($wpdb->query("UPDATE $observacoes SET data_registro = DATE(created_at) WHERE data_registro IS NULL") !== false) {
            update_option('icd_visitas_db_version', self::SCHEMA_VERSION);
        }
    }
    private static function migrate_legacy(){
        global $wpdb; if(get_option('icd_visitas_legacy_migrated')) return;
        $map=['municipios'=>'municipios','escolas'=>'escolas','estagiarias'=>'mediadores','responsaveis'=>'responsaveis'];
        foreach($map as $old=>$new){ $ot=self::old_table($old); $nt=self::table($new); if(!self::exists($ot)) continue;
            if($old==='estagiarias') $wpdb->query("INSERT IGNORE INTO $nt (id,wp_user_id,nome,email,telefone,observacoes,ativo,created_at,updated_at) SELECT id,wp_user_id,nome,email,telefone,observacoes,ativo,created_at,updated_at FROM $ot");
            else $wpdb->query("INSERT IGNORE INTO $nt SELECT * FROM $ot");
        }
        $ov=self::old_table('vinculos'); $nv=self::table('vinculos'); if(self::exists($ov)) $wpdb->query("INSERT IGNORE INTO $nv (id,escola_id,mediador_id,data_inicio,data_fim,ativo,observacoes,created_at,updated_at) SELECT id,escola_id,estagiaria_id,data_inicio,data_fim,ativo,observacoes,created_at,updated_at FROM $ov");
        $oa=self::old_table('agendas'); $na=self::table('agendas'); if(self::exists($oa)) $wpdb->query("INSERT IGNORE INTO $na SELECT * FROM $oa");
        $ovisit=self::old_table('visitas'); $nvisit=self::table('visitas'); if(self::exists($ovisit)) $wpdb->query("INSERT IGNORE INTO $nvisit (id,escola_id,data_visita,turno,hora_inicio,hora_fim,status,tipo,observacoes,criado_por,created_at,updated_at) SELECT id,escola_id,data_visita,turno,hora_inicio,hora_fim,status,tipo,observacoes,criado_por,created_at,updated_at FROM $ovisit");
        $op=self::old_table('participantes'); $np=self::table('participantes'); if(self::exists($op)) $wpdb->query("INSERT IGNORE INTO $np (id,visita_id,mediador_id,presente,observacoes,created_at) SELECT id,visita_id,estagiaria_id,presente,observacoes,created_at FROM $op");
        update_option('icd_visitas_legacy_migrated',current_time('mysql'));
    }
}
