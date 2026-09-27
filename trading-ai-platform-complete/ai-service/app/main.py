from fastapi import FastAPI
from pydantic import BaseModel
from .news import score_news
from .market import analyze_market

app=FastAPI(title="Trading AI Service", version="0.1.0")

class AnalysisRequest(BaseModel):
    symbol:str
    market:dict={}
    news:list=[]

@app.get("/health")
def health(): return {"status":"ok","mode":"analysis_only"}

@app.post("/analyze")
def analyze(req:AnalysisRequest):
    market=analyze_market(req.market)
    news=score_news(req.news)
    return {
        "symbol":req.symbol,
        "market":market,
        "news":news,
        "signal":"HOLD",
        "confidence":0.0,
        "execution_allowed":False
    }
