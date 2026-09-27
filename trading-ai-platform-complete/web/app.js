async function get(path, opts={}){const r=await fetch(path,opts);return r.json()}
async function load(){
 const d=await get('/api/dashboard'); document.getElementById('mode').textContent=d.mode.toUpperCase();
 document.getElementById('stats').textContent=`Estratégias: ${d.stats.strategies}\nPosições: ${d.stats.active_positions}\nP&L paper: ${d.stats.paper_pnl}`;
 const risk=await get('/api/risk'); document.getElementById('risk').textContent=`Máx. ordem: ${risk.max_order_value}\nPerda diária: ${risk.max_daily_loss}\nPosições: ${risk.max_open_positions}`;
 const s=await get('/api/subscription'); document.getElementById('subscription').textContent=`Plano: ${s.plan}\nStatus: ${s.status}`;
 loadStrategies();
}
async function loadStrategies(){const s=await get('/api/strategies');document.getElementById('strategies').textContent=JSON.stringify(s,null,2)}
async function simulate(){const d=await get('/api/simulate/order',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({symbol:'BTCUSDT',side:'BUY',quantity:.001,price:50000})});document.getElementById('result').textContent=JSON.stringify(d,null,2)}
async function killSwitch(){const d=await get('/api/kill-switch',{method:'POST'});document.getElementById('result').textContent=JSON.stringify(d,null,2)}
load();
