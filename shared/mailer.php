<?php
declare(strict_types=1);

/** @return list<string> */
function lteco_smtp_configuration_errors(): array
{
    $errors = [];
    $host = trim((string) configEnv('LTECO_MAIL_HOST', ''));
    $port = (int) configEnv('LTECO_MAIL_PORT', '587');
    $user = trim((string) configEnv('LTECO_MAIL_USER', ''));
    $password = (string) configEnv('LTECO_MAIL_PASS', '');
    $from = trim((string) configEnv('LTECO_MAIL_FROM', $user));
    if ($host === '') $errors[] = 'LTECO_MAIL_HOST';
    if ($port < 1 || $port > 65535) $errors[] = 'LTECO_MAIL_PORT';
    if ($user === '') $errors[] = 'LTECO_MAIL_USER';
    if ($password === '') $errors[] = 'LTECO_MAIL_PASS';
    if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) $errors[] = 'LTECO_MAIL_FROM';
    return $errors;
}

/** @param list<string> $destinatarios */
function lteco_smtp_send_recipients(array $destinatarios, string $asunto, string $cuerpoHtml): bool
{
    if (lteco_smtp_configuration_errors() !== []) return false;
    $host=(string)configEnv('LTECO_MAIL_HOST','');$puerto=(int)configEnv('LTECO_MAIL_PORT','587');
    $usuario=(string)configEnv('LTECO_MAIL_USER','');$clave=(string)configEnv('LTECO_MAIL_PASS','');
    $desde=(string)configEnv('LTECO_MAIL_FROM',$usuario);$nombre=(string)configEnv('LTECO_MAIL_NAME',appName());
    $destinatarios=array_values(array_unique(array_filter(array_map('trim',$destinatarios),static fn(string $d):bool=>filter_var($d,FILTER_VALIDATE_EMAIL)!==false)));
    if($usuario===''||$clave===''||$destinatarios===[])return false;
    try{
        $sock=@stream_socket_client("tcp://{$host}:{$puerto}",$errno,$errstr,10);if(!$sock)return false;stream_set_timeout($sock,10);
        $leer=static function()use($sock):string{$last='';while(($line=fgets($sock,512))!==false){$last=$line;if(strlen($line)>=4&&$line[3]===' ')break;}return $last;};
        $cmd=static function(string $c)use($sock,$leer):string{fwrite($sock,$c."\r\n");return $leer();};
        $leer();$ehlo=(string)configEnv('LTECO_MAIL_EHLO_HOST','example.com');$cmd("EHLO {$ehlo}");
        if(!str_starts_with(trim($cmd('STARTTLS')),'220')){fclose($sock);return false;}
        if(!stream_socket_enable_crypto($sock,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($sock);return false;}
        $cmd("EHLO {$ehlo}");$cmd('AUTH LOGIN');$cmd(base64_encode($usuario));
        if(!str_starts_with(trim($cmd(base64_encode($clave))),'235')){fclose($sock);return false;}
        if(!str_starts_with(trim($cmd("MAIL FROM:<{$desde}>")),'250')){fclose($sock);return false;}foreach($destinatarios as$destino){if(!str_starts_with(trim($cmd("RCPT TO:<{$destino}>")),'250')){fclose($sock);return false;}}
        if(!str_starts_with(trim($cmd('DATA')),'354')){fclose($sock);return false;}$asuntoEnc='=?UTF-8?B?'.base64_encode($asunto).'?=';
        $messageDomain = preg_replace('/[^A-Za-z0-9.-]+/', '', (string)configEnv('LTECO_MAIL_MESSAGE_ID_DOMAIN', $ehlo)) ?: 'example.com';
        $mensaje='From: '.$nombre.' <'.$desde.">\r\nTo: ".implode(', ',$destinatarios)."\r\nSubject: {$asuntoEnc}\r\nDate: ".date('r')."\r\nMessage-ID: <".bin2hex(random_bytes(12))."@".$messageDomain.">\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($cuerpoHtml));
        fwrite($sock,$mensaje."\r\n.\r\n");$respuesta=$leer();$cmd('QUIT');fclose($sock);return str_starts_with(trim($respuesta),'250');
    }catch(Throwable $e){error_log('[MAIL] '.$e->getMessage());return false;}
}

function lteco_smtp_send(string $asunto,string $cuerpoHtml,?string $destinatariosExtra=null):bool
{
    $base=(string)configEnv('LTECO_MAIL_TO','');
    return lteco_smtp_send_recipients(array_filter(array_map('trim',explode(',',$base.($destinatariosExtra?','.$destinatariosExtra:'')))),$asunto,$cuerpoHtml);
}

function notificar(string $asunto,string $titulo,string $cuerpo):void
{
    $html='<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px"><div style="max-width:480px;margin:auto;background:#fff;border-radius:8px;overflow:hidden"><div style="background:#0f6b38;padding:18px 24px"><strong style="color:#fff;font-size:18px">'.htmlspecialchars(appName()).'</strong></div><div style="padding:24px"><h2 style="margin:0 0 12px;color:#151f1a">'.htmlspecialchars($titulo).'</h2><div style="color:#334;line-height:1.6">'.$cuerpo.'</div></div></div></body></html>';
    lteco_smtp_send($asunto,$html);
}
