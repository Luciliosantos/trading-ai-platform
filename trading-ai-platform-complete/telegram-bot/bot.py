import os, httpx
from telegram import Update
from telegram.ext import Application, CommandHandler, ContextTypes

TOKEN=os.getenv("TELEGRAM_BOT_TOKEN")
API=os.getenv("API_URL","http://api:8080")

async def api(path, method="GET", payload=None):
    async with httpx.AsyncClient(timeout=10) as c:
        r=await c.request(method, API+path, json=payload)
        return r.json()

async def start(update:Update, context:ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        "🤖 Trading AI Platform\n\n"
        "Modo: PAPER 🧪\n\n"
        "/painel\n/estrategias\n/nova\n/backtest\n/risco\n"
        "/posicoes\n/assinatura\n/simular\n/stop"
    )

async def painel(update, context):
    d=await api("/api/dashboard")
    await update.message.reply_text(f"📊 Painel\nModo: {d['mode']}\nEstratégias: {d['stats']['strategies']}\nPosições: {d['stats']['active_positions']}")

async def estrategias(update, context):
    data=await api("/api/strategies")
    await update.message.reply_text(f"📈 Estratégias cadastradas: {len(data)}")

async def nova(update, context):
    await update.message.reply_text("Use o painel web para montar a estratégia. A API já aceita definição em JSON.")

async def backtest(update, context):
    await update.message.reply_text("🧪 Backtest disponível no painel. Resultados só devem ser usados após validação histórica e fora da amostra.")

async def risco(update, context):
    d=await api("/api/risk")
    await update.message.reply_text(f"🛡️ Risco\nMáx. ordem: {d['max_order_value']}\nPerda diária: {d['max_daily_loss']}\nPosições: {d['max_open_positions']}")

async def posicoes(update, context):
    d=await api("/api/positions")
    await update.message.reply_text(f"📌 Posições: {len(d.get('positions',[]))}")

async def assinatura(update, context):
    d=await api("/api/subscription")
    await update.message.reply_text(f"💳 Plano: {d['plan']}\nStatus: {d['status']}")

async def simular(update, context):
    order={"symbol":"BTCUSDT","side":"BUY","quantity":0.001,"price":50000}
    d=await api("/api/simulate/order","POST",order)
    await update.message.reply_text(f"🧪 {d}")

async def stop(update, context):
    d=await api("/api/kill-switch","POST",{})
    await update.message.reply_text("🚨 KILL SWITCH ATIVADO: novas ordens estão bloqueadas.")

def main():
    if not TOKEN: raise RuntimeError("TELEGRAM_BOT_TOKEN não configurado")
    app=Application.builder().token(TOKEN).build()
    for cmd, fn in [
        ("start",start),("painel",painel),("estrategias",estrategias),("nova",nova),
        ("backtest",backtest),("risco",risco),("posicoes",posicoes),
        ("assinatura",assinatura),("simular",simular),("stop",stop)
    ]: app.add_handler(CommandHandler(cmd,fn))
    app.run_polling()

if __name__=="__main__": main()
