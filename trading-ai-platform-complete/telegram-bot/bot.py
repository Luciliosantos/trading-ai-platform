import os
import httpx
import logging

from telegram import (
    Update,
    InlineKeyboardButton,
    InlineKeyboardMarkup,
)
from telegram.ext import (
    Application,
    CommandHandler,
    CallbackQueryHandler,
    ContextTypes,
)

logging.basicConfig(level=logging.INFO)
log = logging.getLogger(__name__)

TOKEN = os.getenv("TELEGRAM_BOT_TOKEN")
API = os.getenv("API_URL", "http://api:8080")


async def api(path, method="GET", payload=None):
    async with httpx.AsyncClient(timeout=15) as client:
        response = await client.request(
            method, API + path, json=payload
        )
        response.raise_for_status()
        return response.json()


def menu():
    keyboard = [
        [
            InlineKeyboardButton("📊 Mercado", callback_data="mercado"),
            InlineKeyboardButton("🧠 Estratégias", callback_data="estrategias"),
        ],
        [
            InlineKeyboardButton("🧪 Backtest", callback_data="backtest"),
            InlineKeyboardButton("🛡️ Gerenciar risco", callback_data="risco"),
        ],
        [
            InlineKeyboardButton("📌 Posições", callback_data="posicoes"),
            InlineKeyboardButton("💳 Assinatura", callback_data="assinatura"),
        ],
        [
            InlineKeyboardButton("🌐 Abrir painel", url="http://191.252.113.230"),
        ],
        [
            InlineKeyboardButton("🔄 Atualizar menu", callback_data="menu"),
        ],
    ]
    return InlineKeyboardMarkup(keyboard)


async def show_menu(update, context, edit=False):
    text = (
        "🤖 TRADING AI PLATFORM\n"
        "━━━━━━━━━━━━━━━━━━\n"
        "🟢 Ambiente: DEMO\n"
        "🔒 Ordens reais desativadas\n\n"
        "Escolha uma opção abaixo:"
    )

    if edit and update.callback_query:
        await update.callback_query.edit_message_text(
            text, reply_markup=menu()
        )
    elif update.effective_message:
        await update.effective_message.reply_text(
            text, reply_markup=menu()
        )


async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    await show_menu(update, context)


async def buttons(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()
    action = query.data

    if action == "menu":
        await show_menu(update, context, edit=True)
        return

    try:
        if action == "mercado":
            data = await api("/api/market/ticker")
            await query.edit_message_text(
                "📊 MERCADO\n━━━━━━━━━━━━━━━━━━\n"
                f"{data}",
                reply_markup=InlineKeyboardMarkup([[
                    InlineKeyboardButton("⬅️ Menu", callback_data="menu")
                ]])
            )

        elif action == "estrategias":
            data = await api("/api/strategies")
            if isinstance(data, dict):
                items = data.get("strategies", data.get("data", []))
            else:
                items = data

            lines = ["🧠 ESTRATÉGIAS", "━━━━━━━━━━━━━━━━━━"]
            for item in items[:15]:
                if isinstance(item, dict):
                    lines.append(
                        f"• {item.get('name', 'Estratégia')} "
                        f"({item.get('status', 'DEMO')})"
                    )
            if len(lines) == 2:
                lines.append("Nenhuma estratégia cadastrada.")
            text = "\n".join(lines)

            await query.edit_message_text(
                text,
                reply_markup=InlineKeyboardMarkup([[
                    InlineKeyboardButton("⬅️ Menu", callback_data="menu")
                ]])
            )

        elif action == "backtest":
            await query.edit_message_text(
                "🧪 BACKTEST HISTÓRICO\n\n"
                "Execute os testes no painel web para selecionar "
                "ativo, estratégia e período.\n\n"
                "O resultado é uma simulação histórica, não garantia "
                "de lucro.",
                reply_markup=InlineKeyboardMarkup([
                    [InlineKeyboardButton(
                        "🌐 Abrir painel",
                        url="http://191.252.113.230"
                    )],
                    [InlineKeyboardButton("⬅️ Menu", callback_data="menu")]
                ])
            )

        elif action == "risco":
            data = await api("/api/risk")
            await query.edit_message_text(
                "🛡️ GERENCIAMENTO DE RISCO\n"
                "━━━━━━━━━━━━━━━━━━\n"
                f"Máximo por ordem: {data.get('max_order_value', 'N/D')}\n"
                f"Perda diária máxima: {data.get('max_daily_loss', 'N/D')}\n"
                f"Máximo de posições: {data.get('max_open_positions', 'N/D')}",
                reply_markup=InlineKeyboardMarkup([[
                    InlineKeyboardButton("⬅️ Menu", callback_data="menu")
                ]])
            )

        elif action == "posicoes":
            data = await api("/api/positions")
            positions = data.get("positions", [])
            text = f"📌 POSIÇÕES\n━━━━━━━━━━━━━━━━━━\nTotal: {len(positions)}"
            await query.edit_message_text(
                text,
                reply_markup=InlineKeyboardMarkup([[
                    InlineKeyboardButton("⬅️ Menu", callback_data="menu")
                ]])
            )

        elif action == "assinatura":
            data = await api("/api/subscription")
            await query.edit_message_text(
                "💳 ASSINATURA\n━━━━━━━━━━━━━━━━━━\n"
                f"Plano: {data.get('plan', 'N/D')}\n"
                f"Status: {data.get('status', 'N/D')}",
                reply_markup=InlineKeyboardMarkup([[
                    InlineKeyboardButton("⬅️ Menu", callback_data="menu")
                ]])
            )

    except Exception as exc:
        log.exception("Falha na ação %s", action)
        await query.edit_message_text(
            f"⚠️ Não consegui consultar essa função agora.\n"
            f"Detalhe: {str(exc)[:250]}",
            reply_markup=InlineKeyboardMarkup([[
                InlineKeyboardButton("⬅️ Menu", callback_data="menu")
            ]])
        )


def main():
    if not TOKEN:
        raise RuntimeError("TELEGRAM_BOT_TOKEN não configurado")

    app = Application.builder().token(TOKEN).build()
    app.add_handler(CommandHandler("start", start))
    app.add_handler(CallbackQueryHandler(buttons))
    app.run_polling()


if __name__ == "__main__":
    main()
