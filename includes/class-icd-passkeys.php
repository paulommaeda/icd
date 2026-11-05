<?php
if (!defined('ABSPATH')) exit;

class ICD_Visitas_Passkeys {
    const NS = 'icd-visitas/v1';

    public static function init(){
        add_action('rest_api_init',[__CLASS__,'routes']);
    }

    public static function routes(){
        register_rest_route(self::NS,'/passkeys/register/options',[
            'methods'=>'POST','callback'=>[__CLASS__,'register_options'],
            'permission_callback'=>function(){ return current_user_can('icd_visitas_view_dashboard'); }
        ]);
        register_rest_route(self::NS,'/passkeys/register/verify',[
            'methods'=>'POST','callback'=>[__CLASS__,'register_verify'],
            'permission_callback'=>function(){ return current_user_can('icd_visitas_view_dashboard'); }
        ]);
        register_rest_route(self::NS,'/passkeys',[
            'methods'=>'GET','callback'=>[__CLASS__,'list_passkeys'],
            'permission_callback'=>function(){ return current_user_can('icd_visitas_view_dashboard'); }
        ]);
        register_rest_route(self::NS,'/passkeys/(?P<id>\d+)/delete',[
            'methods'=>'POST','callback'=>[__CLASS__,'delete_passkey'],
            'permission_callback'=>function(){ return current_user_can('icd_visitas_view_dashboard'); }
        ]);
        register_rest_route(self::NS,'/auth/passkey/options',[
            'methods'=>'POST','callback'=>[__CLASS__,'auth_options'],
            'permission_callback'=>'__return_true'
        ]);
        register_rest_route(self::NS,'/auth/passkey/verify',[
            'methods'=>'POST','callback'=>[__CLASS__,'auth_verify'],
            'permission_callback'=>'__return_true'
        ]);
        register_rest_route(self::NS,'/auth/password',[
            'methods'=>'POST','callback'=>[__CLASS__,'password_login'],
            'permission_callback'=>'__return_true'
        ]);
    }

    public static function table(){ return ICD_Visitas_Database::table('passkeys'); }

    private static function recent_auth_key($user_id=0){
        $user_id=$user_id?:get_current_user_id();
        if(!$user_id) return '';
        // Não depende do cookie recém-criado: no login via REST o navegador só recebe
        // o cookie ao final da resposta. O transient por usuário sobrevive ao redirect.
        return 'icd_pk_recent_user_'.(int)$user_id;
    }
    public static function mark_recent_password_auth($user_id=0){
        $key=self::recent_auth_key($user_id); if(!$key) return false;
        return set_transient($key,1,10*MINUTE_IN_SECONDS);
    }
    public static function has_recent_password_auth(){
        $key=self::recent_auth_key(); return $key?(bool)get_transient($key):false;
    }
    public static function clear_recent_password_auth(){
        $key=self::recent_auth_key(); if($key) delete_transient($key);
    }
    public static function current_user_has_passkeys(){
        if(!is_user_logged_in()) return false;
        global $wpdb; $table=self::table();
        return (bool)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM $table WHERE user_id=%d LIMIT 1",get_current_user_id()));
    }
    public static function should_prompt_onboarding(){
        return is_user_logged_in() && !self::current_user_has_passkeys() && self::has_recent_password_auth();
    }

    private static function b64url_encode($data){
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private static function b64url_decode($value){
        if(!is_string($value) || $value==='') return false;
        $value=strtr($value,'-_','+/');
        $pad=strlen($value)%4;
        if($pad) $value.=str_repeat('=',4-$pad);
        return base64_decode($value,true);
    }
    private static function token($bytes=24){ return self::b64url_encode(random_bytes($bytes)); }
    private static function rp_id(){ return (string) wp_parse_url(home_url('/'), PHP_URL_HOST); }
    private static function allowed_origins(){
        $origins=[];
        foreach([home_url('/'),site_url('/')] as $url){
            $parts=wp_parse_url($url); if(empty($parts['scheme'])||empty($parts['host'])) continue;
            $origin=$parts['scheme'].'://'.$parts['host'];
            if(!empty($parts['port'])) $origin.=':'.$parts['port'];
            $origins[]=$origin;
        }
        return array_values(array_unique($origins));
    }
    private static function is_https_site(){ return strtolower((string)wp_parse_url(home_url('/'),PHP_URL_SCHEME))==='https'; }
    private static function pem_from_spki($der){
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";
    }
    private static function fail($message,$status=400){ return new WP_Error('icd_passkey_error',$message,['status'=>$status]); }
    private static function body(WP_REST_Request $request){
        $data=$request->get_json_params(); return is_array($data)?$data:[];
    }
    private static function check_client_data($encoded,$challenge,$type){
        $raw=self::b64url_decode($encoded); if($raw===false) return self::fail('Resposta WebAuthn inválida.');
        $json=json_decode($raw,true); if(!is_array($json)) return self::fail('Dados do navegador inválidos.');
        if(($json['type']??'')!==$type) return self::fail('Tipo de autenticação WebAuthn inválido.');
        if(!isset($json['challenge']) || !hash_equals((string)$challenge,(string)$json['challenge'])) return self::fail('Desafio de autenticação inválido ou expirado.');
        if(empty($json['origin']) || !in_array($json['origin'],self::allowed_origins(),true)) return self::fail('Origem da autenticação não autorizada.');
        return ['raw'=>$raw,'json'=>$json];
    }
    private static function check_authenticator_data($encoded,$require_uv=true){
        $raw=self::b64url_decode($encoded); if($raw===false || strlen($raw)<37) return self::fail('Dados do autenticador inválidos.');
        $expected=hash('sha256',self::rp_id(),true);
        if(!hash_equals($expected,substr($raw,0,32))) return self::fail('Este dispositivo respondeu para outro domínio.');
        $flags=ord($raw[32]);
        if(($flags & 0x01)!==0x01) return self::fail('A presença do usuário não foi confirmada.');
        if($require_uv && (($flags & 0x04)!==0x04)) return self::fail('A biometria/PIN do dispositivo não foi confirmada.');
        $unpacked=unpack('Ncounter',substr($raw,33,4));
        return ['raw'=>$raw,'flags'=>$flags,'sign_count'=>(int)($unpacked['counter']??0)];
    }
    private static function public_key_valid($pem){
        if(!function_exists('openssl_pkey_get_public')) return false;
        $key=@openssl_pkey_get_public($pem); if(!$key) return false;
        if(is_resource($key)) openssl_free_key($key);
        return true;
    }

    public static function register_options(WP_REST_Request $request){
        if(!self::is_https_site()) return self::fail('Passkeys exigem que o site esteja em HTTPS.',400);
        $user=wp_get_current_user();
        $data=self::body($request); $password=(string)($data['password']??'');
        $password_ok=$password && wp_check_password($password,$user->user_pass,$user->ID);
        if(!$password_ok && !self::has_recent_password_auth()) return self::fail('Por segurança, saia e entre novamente com sua senha para ativar a biometria neste aparelho.',401);
        $challenge=self::token(32); $flow=self::token(18);
        set_transient('icd_pk_reg_'.$flow,['user_id'=>(int)$user->ID,'challenge'=>$challenge],5*MINUTE_IN_SECONDS);
        global $wpdb; $table=self::table();
        $existing=$wpdb->get_results($wpdb->prepare("SELECT credential_id,transports FROM $table WHERE user_id=%d ORDER BY id",$user->ID));
        $exclude=[];
        foreach($existing as $row){
            $item=['type'=>'public-key','id'=>$row->credential_id];
            $tr=json_decode((string)$row->transports,true); if(is_array($tr)&&$tr)$item['transports']=$tr;
            $exclude[]=$item;
        }
        $user_handle=self::b64url_encode(hash('sha256','icd-passkey-user:'.$user->ID,true));
        return rest_ensure_response([
            'flow'=>$flow,
            'publicKey'=>[
                'challenge'=>$challenge,
                'rp'=>['name'=>'Instituto Caminho de Davi','id'=>self::rp_id()],
                'user'=>['id'=>$user_handle,'name'=>$user->user_login,'displayName'=>$user->display_name ?: $user->user_login],
                'pubKeyCredParams'=>[['type'=>'public-key','alg'=>-7],['type'=>'public-key','alg'=>-257]],
                'timeout'=>60000,
                'attestation'=>'none',
                'authenticatorSelection'=>[
                    'authenticatorAttachment'=>'platform',
                    'residentKey'=>'required',
                    'requireResidentKey'=>true,
                    'userVerification'=>'required'
                ],
                'excludeCredentials'=>$exclude
            ]
        ]);
    }

    public static function register_verify(WP_REST_Request $request){
        $data=self::body($request); $flow=sanitize_text_field($data['flow']??'');
        $state=$flow?get_transient('icd_pk_reg_'.$flow):false;
        if(!$state || (int)$state['user_id']!==get_current_user_id()) return self::fail('Cadastro de biometria expirado. Tente novamente.');
        delete_transient('icd_pk_reg_'.$flow);
        $client=self::check_client_data((string)($data['clientDataJSON']??''),(string)$state['challenge'],'webauthn.create'); if(is_wp_error($client)) return $client;
        $auth=self::check_authenticator_data((string)($data['authenticatorData']??''),true); if(is_wp_error($auth)) return $auth;
        $credential_id=sanitize_text_field($data['credentialId']??'');
        if(!$credential_id || strlen($credential_id)>512 || self::b64url_decode($credential_id)===false) return self::fail('Identificador da Passkey inválido.');
        $der=self::b64url_decode((string)($data['publicKey']??'')); if($der===false || strlen($der)<50 || strlen($der)>4096) return self::fail('Chave pública inválida.');
        $pem=self::pem_from_spki($der); if(!self::public_key_valid($pem)) return self::fail('Não foi possível validar a chave pública gerada pelo dispositivo.');
        $alg=(int)($data['algorithm']??0); if(!in_array($alg,[-7,-257],true)) return self::fail('Algoritmo criptográfico não suportado neste dispositivo.');
        $device=sanitize_text_field($data['deviceName']??'Este dispositivo'); if(!$device)$device='Este dispositivo'; $device=function_exists('mb_substr')?mb_substr($device,0,190):substr($device,0,190);
        $transports=[]; if(!empty($data['transports'])&&is_array($data['transports'])) foreach($data['transports'] as $tr){$tr=sanitize_key($tr);if(in_array($tr,['usb','nfc','ble','internal','hybrid'],true))$transports[]=$tr;}
        global $wpdb; $table=self::table(); $hash=hash('sha256',$credential_id); $uid=get_current_user_id();
        $existing=$wpdb->get_row($wpdb->prepare("SELECT id,user_id FROM $table WHERE credential_hash=%s",$hash));
        if($existing && (int)$existing->user_id!==$uid) return self::fail('Esta Passkey já está vinculada a outro usuário.',409);
        $row=[
            'user_id'=>$uid,'credential_hash'=>$hash,'credential_id'=>$credential_id,'public_key'=>$pem,'algorithm'=>$alg,
            'sign_count'=>$auth['sign_count'],'transports'=>wp_json_encode(array_values(array_unique($transports))),
            'device_name'=>$device,'updated_at'=>current_time('mysql')
        ];
        if($existing){ $wpdb->update($table,$row,['id'=>(int)$existing->id]); $id=(int)$existing->id; }
        else { $row['created_at']=current_time('mysql'); $wpdb->insert($table,$row); $id=(int)$wpdb->insert_id; }
        if(!$id) return self::fail('Não foi possível salvar a Passkey.',500);
        self::clear_recent_password_auth();
        return rest_ensure_response(['success'=>true,'message'=>'Login por biometria/dispositivo ativado.','id'=>$id,'deviceName'=>$device]);
    }

    public static function list_passkeys(){
        global $wpdb; $table=self::table();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,device_name,created_at,last_used_at FROM $table WHERE user_id=%d ORDER BY created_at DESC",get_current_user_id()),ARRAY_A);
        return rest_ensure_response(['items'=>$rows]);
    }

    public static function delete_passkey(WP_REST_Request $request){
        global $wpdb; $table=self::table(); $id=absint($request['id']);
        $deleted=$wpdb->delete($table,['id'=>$id,'user_id'=>get_current_user_id()],['%d','%d']);
        if(!$deleted) return self::fail('Passkey não encontrada.',404);
        return rest_ensure_response(['success'=>true]);
    }

    public static function auth_options(WP_REST_Request $request){
        if(!self::is_https_site()) return self::fail('O login por biometria exige HTTPS.',400);
        $challenge=self::token(32); $flow=self::token(18);
        set_transient('icd_pk_auth_'.$flow,['challenge'=>$challenge],3*MINUTE_IN_SECONDS);
        return rest_ensure_response(['flow'=>$flow,'publicKey'=>[
            'challenge'=>$challenge,'rpId'=>self::rp_id(),'timeout'=>60000,'userVerification'=>'required'
        ]]);
    }

    public static function auth_verify(WP_REST_Request $request){
        if(!function_exists('openssl_verify')) return self::fail('O servidor PHP precisa da extensão OpenSSL para validar Passkeys.',500);
        $data=self::body($request); $flow=sanitize_text_field($data['flow']??'');
        $state=$flow?get_transient('icd_pk_auth_'.$flow):false;
        if(!$state) return self::fail('Tentativa de login expirada. Tente novamente.');
        delete_transient('icd_pk_auth_'.$flow);
        $client=self::check_client_data((string)($data['clientDataJSON']??''),(string)$state['challenge'],'webauthn.get'); if(is_wp_error($client)) return $client;
        $auth=self::check_authenticator_data((string)($data['authenticatorData']??''),true); if(is_wp_error($auth)) return $auth;
        $credential_id=sanitize_text_field($data['credentialId']??''); if(!$credential_id) return self::fail('Passkey não identificada.');
        global $wpdb; $table=self::table(); $hash=hash('sha256',$credential_id);
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE credential_hash=%s",$hash));
        if(!$row || !hash_equals((string)$row->credential_id,$credential_id)) return self::fail('Esta Passkey não está cadastrada neste sistema.',404);
        $signature=self::b64url_decode((string)($data['signature']??'')); if($signature===false) return self::fail('Assinatura inválida.');
        $signed=$auth['raw'].hash('sha256',$client['raw'],true);
        $key=@openssl_pkey_get_public($row->public_key); if(!$key) return self::fail('Chave pública cadastrada inválida.',500);
        $verified=openssl_verify($signed,$signature,$key,OPENSSL_ALGO_SHA256); if(is_resource($key))openssl_free_key($key);
        if($verified!==1) return self::fail('Não foi possível confirmar a assinatura deste dispositivo.',401);
        $old=(int)$row->sign_count; $new=(int)$auth['sign_count'];
        if($old>0 && $new>0 && $new<=$old) return self::fail('O contador de segurança desta Passkey não avançou. Remova e cadastre o dispositivo novamente.',401);
        $user=get_user_by('id',(int)$row->user_id); if(!$user) return self::fail('Usuário vinculado à Passkey não existe.',404);
        if(!user_can($user,'icd_visitas_view_dashboard')) return self::fail('Este usuário não possui acesso ao sistema de visitas.',403);
        $wpdb->update($table,['sign_count'=>max($old,$new),'last_used_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')],['id'=>(int)$row->id]);
        wp_set_current_user($user->ID); wp_set_auth_cookie($user->ID,true,is_ssl()); do_action('wp_login',$user->user_login,$user);
        $redirect=ICD_Visitas_Portal::login_destination($data['redirect']??'', $user);
        return rest_ensure_response(['success'=>true,'redirect'=>$redirect,'displayName'=>$user->display_name]);
    }

    private static function login_rate_key(){
        $ip=sanitize_text_field($_SERVER['REMOTE_ADDR']??'unknown'); return 'icd_login_fail_'.substr(hash('sha256',$ip),0,24);
    }

    public static function password_login(WP_REST_Request $request){
        $rate_key=self::login_rate_key(); $fails=(int)get_transient($rate_key);
        if($fails>=7) return self::fail('Muitas tentativas de login. Aguarde alguns minutos e tente novamente.',429);
        $data=self::body($request); $username=sanitize_text_field($data['username']??''); $password=(string)($data['password']??'');
        if(!$username || !$password) return self::fail('Informe usuário/e-mail e senha.');
        $login=$username;
        if(is_email($username)){ $u=get_user_by('email',$username); if($u)$login=$u->user_login; }
        $user=wp_signon(['user_login'=>$login,'user_password'=>$password,'remember'=>true],is_ssl());
        if(is_wp_error($user)){
            set_transient($rate_key,$fails+1,15*MINUTE_IN_SECONDS);
            return self::fail('Usuário/e-mail ou senha inválidos.',401);
        }
        if(!user_can($user,'icd_visitas_view_dashboard')){ wp_clear_auth_cookie(); wp_set_current_user(0); return self::fail('Este usuário não possui acesso ao sistema de visitas.',403); }
        delete_transient($rate_key);
        self::mark_recent_password_auth($user->ID);
        $redirect=ICD_Visitas_Portal::login_destination($data['redirect']??'', $user);
        return rest_ensure_response(['success'=>true,'redirect'=>$redirect,'displayName'=>$user->display_name]);
    }

    public static function current_user_passkeys(){
        if(!is_user_logged_in()) return [];
        global $wpdb; $table=self::table();
        return $wpdb->get_results($wpdb->prepare("SELECT id,device_name,created_at,last_used_at FROM $table WHERE user_id=%d ORDER BY created_at DESC",get_current_user_id()));
    }
}
