CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    name TEXT NOT NULL,
    language TEXT NOT NULL DEFAULT 'uk' CHECK (language IN ('uk','en','de')),
    notifications INTEGER NOT NULL DEFAULT 0 CHECK (notifications IN (0,1))
);
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    storage TEXT NOT NULL,
    expires_on TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','consumed','discarded')),
    request_token TEXT NOT NULL,
    UNIQUE(user_id, request_token)
);
CREATE INDEX IF NOT EXISTS products_owner_expiry ON products(user_id, status, expires_on);
CREATE TABLE IF NOT EXISTS subscriptions (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    endpoint TEXT NOT NULL UNIQUE,
    public_key TEXT NOT NULL,
    auth_token TEXT NOT NULL,
    session_key TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS deliveries (
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    subscription_id INTEGER NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
    expires_on TEXT NOT NULL,
    sent_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(product_id, subscription_id, expires_on)
);
CREATE TABLE IF NOT EXISTS auth_attempts (
    bucket TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL,
    started_at INTEGER NOT NULL
);
