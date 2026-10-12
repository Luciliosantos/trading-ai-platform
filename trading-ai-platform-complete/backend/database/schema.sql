CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  telegram_id BIGINT UNIQUE,
  email VARCHAR(255) UNIQUE,
  name VARCHAR(255),
  role VARCHAR(30) NOT NULL DEFAULT 'customer',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS plans (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  price_cents INTEGER NOT NULL DEFAULT 0,
  interval VARCHAR(20) NOT NULL DEFAULT 'month',
  features JSONB NOT NULL DEFAULT '{}'::jsonb
);

CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id),
  plan_id BIGINT REFERENCES plans(id),
  status VARCHAR(30) NOT NULL DEFAULT 'trial',
  expires_at TIMESTAMPTZ,
  provider VARCHAR(50),
  provider_id VARCHAR(255),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS strategies (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id),
  name VARCHAR(255) NOT NULL,
  definition JSONB NOT NULL DEFAULT '{}'::jsonb,
  status VARCHAR(30) NOT NULL DEFAULT 'paper',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS risk_profiles (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT UNIQUE REFERENCES users(id),
  max_order_value NUMERIC(20,8) NOT NULL DEFAULT 100,
  max_daily_loss NUMERIC(20,8) NOT NULL DEFAULT 300,
  max_open_positions INTEGER NOT NULL DEFAULT 3,
  live_enabled BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS orders (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id),
  connector VARCHAR(80) NOT NULL,
  symbol VARCHAR(80) NOT NULL,
  side VARCHAR(10) NOT NULL,
  quantity NUMERIC(30,12) NOT NULL,
  price NUMERIC(30,12),
  status VARCHAR(40) NOT NULL,
  external_id VARCHAR(255),
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT,
  action VARCHAR(100) NOT NULL,
  payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO plans(name,price_cents,features) VALUES
('Starter',0,'{"paper":true,"backtest":true,"telegram":true}'),
('Pro',0,'{"paper":true,"backtest":true,"telegram":true,"ai":true,"news":true}'),
('Advanced',0,'{"paper":true,"backtest":true,"telegram":true,"ai":true,"news":true,"copy":true,"arbitrage":true}')
ON CONFLICT (name) DO NOTHING;


CREATE TABLE IF NOT EXISTS user_credentials (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Autenticação de clientes
CREATE TABLE IF NOT EXISTS auth_sessions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_auth_sessions_user
    ON auth_sessions(user_id);

CREATE INDEX IF NOT EXISTS idx_auth_sessions_expiry
    ON auth_sessions(expires_at);

-- Contas de corretoras cadastradas pelos clientes.
-- As chaves serão armazenadas criptografadas na aplicação.
CREATE TABLE IF NOT EXISTS exchange_accounts (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    exchange VARCHAR(40) NOT NULL,
    label VARCHAR(100) NOT NULL,
    api_key_encrypted TEXT NOT NULL,
    api_secret_encrypted TEXT NOT NULL,
    environment VARCHAR(20) NOT NULL DEFAULT 'demo',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_exchange_accounts_user
    ON exchange_accounts(user_id);

-- Estratégias pertencem ao cliente que as criou.
CREATE INDEX IF NOT EXISTS idx_strategies_user
    ON strategies(user_id);
