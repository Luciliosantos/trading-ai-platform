# Connectors

Cada plataforma deve implementar o contrato de capacidades definido no backend.

Conectores reais devem:
- usar API oficial/permissão válida;
- suportar sandbox quando disponível;
- não aceitar withdrawal permission;
- validar símbolos e quantidade;
- registrar request/response sem expor secrets;
- passar pelo Risk Manager.

Conectores previstos:
- Binance
- Bybit
- B3/WIN/WDO
- outras APIs permitidas

IQ Option e ExpertOption ficam apenas como pontos de extensão até existir uma integração oficial/permitida adequada. Não usar credenciais em APIs não autorizadas.
