<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
const MAX_ORDERS = 60;
function fail(string $message, int $status=422): never { throw new RuntimeException($message,$status); }
function keySecret(): string { return getenv('DEMO_SECRET') ?: 'commerceops-public-demo-not-a-security-secret-v1'; }
function encodeState(array $s): string { $p=base64_encode(json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)); return $p.'.'.hash_hmac('sha256',$p,keySecret()); }
function decodeState(string $token): array {
    if(strlen($token)>500000) fail('Sessão excedeu o limite. Reinicie a demonstração.',413);
    $parts=explode('.',$token); if(count($parts)!==2 || !hash_equals(hash_hmac('sha256',$parts[0],keySecret()),$parts[1])) fail('Sessão inválida. Reinicie a demonstração.',401);
    $s=json_decode(base64_decode($parts[0],true) ?: '',true,64,JSON_THROW_ON_ERROR);
    if(!is_array($s)||($s['version']??0)!==1||!isset($s['products'],$s['orders'],$s['events'],$s['next'])) fail('Sessão inválida.',401);
    return $s;
}
function product(string $id,string $name,string $cat,int $price,int $stock,string $icon,string $color):array {return compact('id','name','cat','price','stock','icon','color');}
function seed():array {
    $p=[product('SKU-101','Headphone Studio','Áudio',24990,24,'headphones','violet'),product('SKU-102','Teclado Mecânico','Periféricos',32990,15,'keyboard','blue'),product('SKU-103','Mouse Wireless','Periféricos',12990,6,'mouse','mint'),product('SKU-104','Hub USB-C 7 em 1','Acessórios',18990,3,'usb','orange'),product('SKU-105','Monitor 24” IPS','Monitores',89990,9,'monitor','blue'),product('SKU-106','Webcam Full HD','Acessórios',21990,18,'video','violet')];
    $s=['version'=>1,'products'=>$p,'orders'=>[],'events'=>[],'next'=>1005];
    $rows=[[1001,'Cliente Demo A',0,1,'delivered',4],[1002,'Cliente Demo B',1,2,'shipped',3],[1003,'Cliente Demo C',2,1,'paid',1],[1004,'Cliente Demo D',4,1,'pending',0]];
    foreach($rows as [$id,$customer,$idx,$qty,$status,$days]){ $prod=$p[$idx];$subtotal=$prod['price']*$qty; $s['orders'][]=['id'=>'ORD-'.$id,'customer'=>$customer,'cep'=>'89160-000','items'=>[['id'=>$prod['id'],'name'=>$prod['name'],'qty'=>$qty,'price'=>$prod['price']]],'subtotal'=>$subtotal,'discount'=>0,'shipping'=>1990,'total'=>$subtotal+1990,'status'=>$status,'payment'=>$status==='pending'?'pending':'approved','tracking'=>in_array($status,['shipped','delivered'])?'SIM-'.$id:null,'channel'=>$id===1002?'Marketplace':'Loja virtual','created'=>gmdate('c',time()-$days*86400),'history'=>[['status'=>'pending','at'=>gmdate('c',time()-$days*86400)],...($status!=='pending'?[['status'=>$status,'at'=>gmdate('c',time()-$days*86400)]]:[])]]; }
    event($s,'system.ready','Ambiente de demonstração inicializado','success');return $s;
}
function event(array &$s,string $type,string $message,string $level='success',?string $eventId=null):void {array_unshift($s['events'],['id'=>$eventId??bin2hex(random_bytes(6)),'type'=>$type,'message'=>$message,'level'=>$level,'at'=>gmdate('c')]);$s['events']=array_slice($s['events'],0,100);}
function productIndex(array $s,string $id):int {foreach($s['products'] as $i=>$p)if($p['id']===$id)return $i;fail('SKU não encontrado.',404);}
function orderIndex(array $s,string $id):int {foreach($s['orders'] as $i=>$o)if($o['id']===$id)return $i;fail('Pedido não encontrado.',404);}
function strField(array $b,string $name,int $max=80):string { $v=$b[$name]??null;if(!is_string($v)||trim($v)===''||strlen($v)>$max)fail('Campo inválido: '.$name);return trim($v); }
function intField(array $b,string $name,int $min,int $max):int { $v=$b[$name]??null;if(!is_int($v)||$v<$min||$v>$max)fail('Campo inválido: '.$name);return $v; }
function quote(array $s,array $b):array {
    $raw=$b['items']??null;if(!is_array($raw)||count($raw)<1||count($raw)>20)fail('Carrinho inválido.');$cep=preg_replace('/\D/','',strField($b,'cep',12));if(strlen($cep)!==8)fail('Informe um CEP com 8 dígitos.');$qtys=[];
    foreach($raw as $item){if(!is_array($item))fail('Item inválido.');$id=strField($item,'id',32);$qty=intField($item,'qty',1,99);$qtys[$id]=($qtys[$id]??0)+$qty;}
    $items=[];$subtotal=0;foreach($qtys as $id=>$qty){$p=$s['products'][productIndex($s,$id)];if($qty>$p['stock'])fail('Estoque insuficiente para '.$p['name'].'.',409);$items[]=['id'=>$id,'name'=>$p['name'],'qty'=>$qty,'price'=>$p['price']];$subtotal+=$qty*$p['price'];}
    $coupon=$b['coupon']??'';if(!is_string($coupon))fail('Cupom inválido.');$coupon=strtoupper(trim($coupon));if(!in_array($coupon,['','DEMO10'],true))fail('Cupom inválido.');$discount=$coupon==='DEMO10'?intdiv($subtotal,10):0;$shipping=($subtotal-$discount)>=50000?0:(str_starts_with($cep,'89')?1290:2490);$total=$subtotal-$discount+$shipping;return compact('items','subtotal','discount','shipping','total','cep');
}
function changeStatus(array &$o,string $status):void {$o['status']=$status;$o['history'][]=['status'=>$status,'at'=>gmdate('c')];}
function payment(array &$s,array $b):array {
    $idx=orderIndex($s,strField($b,'orderId',32));$result=strField($b,'result',20);if(!in_array($result,['approved','failed'],true))fail('Resultado inválido.');$id=strField($b,'eventId',80);
    foreach($s['orders'] as $existing)foreach($existing['paymentEvents']??[] as $e)if($e['id']===$id){if($existing['id']!==$s['orders'][$idx]['id']||$e['result']!==$result)fail('ID de evento reutilizado com outro conteúdo.',409);return ['duplicate'=>true,'order'=>$s['orders'][$idx]];}
    $o=&$s['orders'][$idx];if($o['status']!=='pending')fail('Pedido não está aguardando pagamento.',409);
    $o['paymentEvents'][]=['id'=>$id,'result'=>$result];$o['payment']=$result;if($result==='approved')changeStatus($o,'paid');event($s,'payment.'.$result,$o['id'].' · '.($result==='approved'?'Pagamento aprovado':'Pagamento recusado; permite nova tentativa'),$result==='approved'?'success':'error',$id);return ['duplicate'=>false,'order'=>$o];
}
$start=microtime(true);
try {
    $action=$_GET['action']??'health'; if(!is_string($action))fail('Rota inválida.',404);
    $method=$_SERVER['REQUEST_METHOD']??'GET'; $routes=['health'=>'GET','bootstrap'=>'GET','state'=>'POST','quote'=>'POST','orders'=>'POST','stock'=>'PATCH','webhook'=>'POST','fulfillment'=>'PATCH','sync'=>'POST'];
    if(!isset($routes[$action]))fail('Rota não encontrada.',404);if($method!==$routes[$action]){header('Allow: '.$routes[$action]);fail('Método não permitido.',405);}
    $body=[]; if($method!=='GET'){ $raw=file_get_contents('php://input',false,null,0,1000001);if(strlen($raw)>1000000)fail('Requisição muito grande.',413);$body=json_decode($raw?:'{}',true,64,JSON_THROW_ON_ERROR);if(!is_array($body))fail('JSON inválido.',400);}
    $status=200;$data=[];$s=null;
    if($action==='health'){$data=['service'=>'CommerceOps API','runtime'=>PHP_VERSION,'mode'=>'sandbox','status'=>'ok'];}
    elseif($action==='bootstrap'){$s=seed();}
    else {
        $token=$body['_demoToken']??($_SERVER['HTTP_X_DEMO_STATE']??'');if(!is_string($token)||!$token)fail('Envie _demoToken no JSON ou o cabeçalho X-Demo-State.',401);unset($body['_demoToken']);$s=decodeState($token);
        switch($action){
            case 'state': break;
            case 'quote': $data=quote($s,$body);break;
            case 'orders':
                if(count($s['orders'])>=MAX_ORDERS)fail('Limite de pedidos da demonstração.',409);
                $customer=strField($body,'customer',80);$q=quote($s,$body);$channel=$body['channel']??'Loja virtual';if(!in_array($channel,['Loja virtual','Marketplace'],true))fail('Canal inválido.');
                foreach($q['items'] as $item)$s['products'][productIndex($s,$item['id'])]['stock']-=$item['qty'];
                $o=array_merge($q,['id'=>'ORD-'.$s['next']++,'customer'=>$customer,'status'=>'pending','payment'=>'pending','tracking'=>null,'channel'=>$channel,'created'=>gmdate('c'),'history'=>[['status'=>'pending','at'=>gmdate('c')]]]);$s['orders'][]=$o;event($s,'order.created',$o['id'].' · estoque reservado');$data=['order'=>$o];$status=201;break;
            case 'stock': $idx=productIndex($s,strField($body,'id',32));$s['products'][$idx]['stock']=intField($body,'stock',0,9999);event($s,'stock.updated',$s['products'][$idx]['id'].' · saldo atualizado');$data=['product'=>$s['products'][$idx]];break;
            case 'webhook': $data=payment($s,$body);break;
            case 'fulfillment':
                $idx=orderIndex($s,strField($body,'orderId',32));$o=&$s['orders'][$idx];$target=strField($body,'status',20);$allowed=['pending'=>['cancelled'],'paid'=>['shipped'],'shipped'=>['delivered']];if(!in_array($target,$allowed[$o['status']]??[],true))fail('Transição de status não permitida.',409);
                if($target==='cancelled'){foreach($o['items'] as $item)$s['products'][productIndex($s,$item['id'])]['stock']+=$item['qty'];$o['payment']='cancelled';}
                if($target==='shipped')$o['tracking']='SIM-'.substr($o['id'],4);changeStatus($o,$target);event($s,'order.'.$target,$o['id'].' · status atualizado');$data=['order'=>$o];unset($o);break;
            case 'sync': $result=strField($body,'result',20);if(!in_array($result,['success','error'],true))fail('Resultado inválido.');event($s,'marketplace.sync',$result==='success'?'Catálogo sincronizado no conector simulado':'Falha simulada de integração: timeout',$result);$data=['synced'=>$result==='success'?count($s['products']):0,'simulated'=>true];break;
        }
    }
    http_response_code($status);$response=['ok'=>true,'data'=>$data,'meta'=>['action'=>$action,'elapsed_ms'=>round((microtime(true)-$start)*1000,2)]];
    if($s!==null){$response['state']=$s;$response['token']=encodeState($s);}echo json_encode($response,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){$status=$e instanceof JsonException?400:(($e->getCode()>=400&&$e->getCode()<=499)?$e->getCode():500);http_response_code($status);echo json_encode(['ok'=>false,'error'=>$status===500?'Erro interno da API.':($e instanceof JsonException?'JSON inválido.':$e->getMessage())],JSON_UNESCAPED_UNICODE);}
