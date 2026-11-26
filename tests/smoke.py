import json,sys,urllib.request,urllib.error
BASE=sys.argv[1].rstrip('/') if len(sys.argv)>1 else 'http://localhost:8000'
token=''; count=0

def req(action,method='GET',data=None,status=200):
    global token,count
    headers={'Accept':'application/json','Content-Type':'application/json'}
    if method!='GET' and token:data={**(data or {}),'_demoToken':token}
    request=urllib.request.Request(BASE+'/api/index.php?action='+action,data=json.dumps(data).encode() if data is not None else None,headers=headers,method=method)
    try:
        with urllib.request.urlopen(request) as response: code=response.status;r=json.load(response)
    except urllib.error.HTTPError as e:code=e.code;r=json.load(e)
    assert code==status,(action,code,r)
    assert r['ok']==(status<400),r
    if 'token' in r:token=r['token']
    count+=1;print(f'PASS {count:02} {method:5} {action:12} {code}')
    return r
req('health');r=req('bootstrap');initial=r['state']['products'][0]['stock'];req('state','POST',{})
base={'items':[{'id':'SKU-101','qty':1}],'cep':'89160-000','coupon':'DEMO10'}
q=req('quote','POST',base)['data'];assert (q['discount'],q['shipping'],q['total'])==(2499,1290,23781)
r=req('orders','POST',{**base,'customer':'Teste'},201);o=r['data']['order']['id'];assert r['state']['products'][0]['stock']==initial-1
req('orders','POST',{**base,'items':[{'id':'SKU-101','qty':99}],'customer':'Teste'},409)
req('quote','POST',{**base,'items':[{'id':'SKU-101','qty':20},{'id':'SKU-101','qty':20}]},409)
req('quote','POST',{**base,'coupon':'INVALID'},422)
req('fulfillment','PATCH',{'orderId':o,'status':'shipped'},409)
r=req('webhook','POST',{'orderId':o,'eventId':'fail-1','result':'failed'});assert r['data']['order']['status']=='pending'
p={'orderId':o,'eventId':'payment-1','result':'approved'};r=req('webhook','POST',p);assert r['data']['order']['status']=='paid';events=len(r['state']['events'])
r=req('webhook','POST',p);assert r['data']['duplicate'] and len(r['state']['events'])==events
req('webhook','POST',{**p,'result':'failed'},409)
r=req('fulfillment','PATCH',{'orderId':o,'status':'shipped'});assert r['data']['order']['tracking'].startswith('SIM-')
r=req('fulfillment','PATCH',{'orderId':o,'status':'delivered'});assert r['data']['order']['status']=='delivered'
r=req('orders','POST',{**base,'customer':'Cancelar'},201);c=r['data']['order']['id'];before=r['state']['products'][0]['stock']
r=req('fulfillment','PATCH',{'orderId':c,'status':'cancelled'});assert r['state']['products'][0]['stock']==before+1
req('fulfillment','PATCH',{'orderId':c,'status':'cancelled'},409)
r=req('stock','PATCH',{'id':'SKU-104','stock':12});assert r['data']['product']['stock']==12
req('stock','PATCH',{'id':'SKU-104','stock':-1},422)
r=req('sync','POST',{'result':'success'});assert r['data']['synced']==6
r=req('sync','POST',{'result':'error'});assert r['state']['events'][0]['level']=='error'
req('health','POST',{},405);req('nonexistent',status=404)
token+='x';req('state','POST',{},401)
print(f'OK — {count} requisições HTTP verificadas.')
