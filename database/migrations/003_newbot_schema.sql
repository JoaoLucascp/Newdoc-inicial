CREATE TABLE newbot_instances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    instance_name TEXT,
    status TEXT NOT NULL DEFAULT 'ativo'
        CHECK (status IN ('ativo', 'inativo', 'manutencao')),
    notes TEXT,
    source_document_id INTEGER REFERENCES documents(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX newbot_instances_one_active_per_company_idx
    ON newbot_instances(company_id)
    WHERE status = 'ativo';
CREATE INDEX newbot_instances_company_idx ON newbot_instances(company_id);
CREATE INDEX newbot_instances_status_idx ON newbot_instances(status);

CREATE TABLE newbot_channels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newbot_instance_id INTEGER NOT NULL REFERENCES newbot_instances(id) ON DELETE CASCADE,
    external_id TEXT,
    channel_name TEXT,
    activation_date TEXT,
    plan TEXT,
    quantity INTEGER CHECK (quantity IS NULL OR quantity >= 0),
    link TEXT,
    status TEXT NOT NULL DEFAULT 'ativo'
        CHECK (status IN ('ativo', 'inativo', 'manutencao')),
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newbot_channels_instance_idx ON newbot_channels(newbot_instance_id);
CREATE INDEX newbot_channels_status_idx ON newbot_channels(status);

CREATE TABLE newbot_administrators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newbot_instance_id INTEGER NOT NULL REFERENCES newbot_instances(id) ON DELETE CASCADE,
    login TEXT,
    password_encrypted TEXT,
    supervisor_login TEXT,
    supervisor_email TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newbot_administrators_instance_idx ON newbot_administrators(newbot_instance_id);

CREATE TABLE newbot_responsibles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newbot_instance_id INTEGER NOT NULL REFERENCES newbot_instances(id) ON DELETE CASCADE,
    name TEXT,
    contact TEXT,
    email TEXT,
    department TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newbot_responsibles_instance_idx ON newbot_responsibles(newbot_instance_id);

CREATE TABLE newbot_authentications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newbot_instance_id INTEGER NOT NULL REFERENCES newbot_instances(id) ON DELETE CASCADE,
    platform TEXT NOT NULL
        CHECK (platform IN ('facebook', 'instagram', 'whatsapp', 'site', 'outro')),
    status TEXT NOT NULL DEFAULT 'pendente'
        CHECK (status IN ('ativo', 'inativo', 'pendente')),
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newbot_authentications_instance_idx ON newbot_authentications(newbot_instance_id);
CREATE INDEX newbot_authentications_status_idx ON newbot_authentications(status);

CREATE TABLE newbot_integrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newbot_instance_id INTEGER NOT NULL REFERENCES newbot_instances(id) ON DELETE CASCADE,
    name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 160),
    username TEXT,
    password_encrypted TEXT,
    link TEXT,
    activation_date TEXT,
    status TEXT NOT NULL DEFAULT 'pendente'
        CHECK (status IN ('ativo', 'inativo', 'pendente')),
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newbot_integrations_instance_idx ON newbot_integrations(newbot_instance_id);
CREATE INDEX newbot_integrations_status_idx ON newbot_integrations(status);

CREATE TABLE newbot_attendants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newbot_instance_id INTEGER NOT NULL REFERENCES newbot_instances(id) ON DELETE CASCADE,
    name TEXT,
    username TEXT,
    email TEXT,
    department TEXT,
    attendant_type TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newbot_attendants_instance_idx ON newbot_attendants(newbot_instance_id);
