CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 2 AND 160),
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'viewer' CHECK (role IN ('admin', 'technician', 'viewer')),
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 2 AND 200),
    trade_name TEXT,
    cnpj TEXT,
    responsible_name TEXT,
    responsible_phone TEXT,
    email TEXT,
    address TEXT,
    contract_start_date TEXT,
    contract_end_date TEXT,
    contract_status TEXT NOT NULL DEFAULT 'inexistente'
        CHECK (contract_status IN ('ativo', 'inativo', 'inexistente')),
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX companies_name_idx ON companies(name COLLATE NOCASE);
CREATE INDEX companies_cnpj_idx ON companies(cnpj);
CREATE INDEX companies_email_idx ON companies(email COLLATE NOCASE);
CREATE INDEX companies_responsible_idx ON companies(responsible_name COLLATE NOCASE);
CREATE INDEX companies_contract_status_idx ON companies(contract_status);

CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
    original_filename TEXT NOT NULL,
    stored_filename TEXT NOT NULL UNIQUE,
    file_path TEXT NOT NULL,
    file_hash_sha256 TEXT NOT NULL UNIQUE CHECK (length(file_hash_sha256) = 64),
    file_size_bytes INTEGER NOT NULL CHECK (file_size_bytes > 0),
    document_type TEXT NOT NULL CHECK (document_type IN ('newsic', 'newbot', 'newvoz', 'contatos', 'outro')),
    source_status TEXT NOT NULL DEFAULT 'pendente'
        CHECK (source_status IN ('pendente', 'em_analise', 'validado', 'historico', 'descartado')),
    is_current INTEGER NOT NULL DEFAULT 0 CHECK (is_current IN (0, 1)),
    imported_at TEXT,
    imported_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    uploaded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX documents_company_id_idx ON documents(company_id);
CREATE INDEX documents_status_idx ON documents(source_status);
CREATE INDEX documents_type_idx ON documents(document_type);
CREATE INDEX documents_hash_idx ON documents(file_hash_sha256);

CREATE TABLE document_field_mappings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    sheet_name TEXT NOT NULL,
    field_group TEXT NOT NULL
        CHECK (field_group IN ('company', 'newsic', 'newbot', 'newvoz', 'network', 'ura', 'routing', 'inventory')),
    field_name TEXT NOT NULL,
    label_found TEXT,
    label_cell TEXT,
    value_cell TEXT,
    value_range TEXT,
    is_required INTEGER NOT NULL DEFAULT 0 CHECK (is_required IN (0, 1)),
    is_sensitive INTEGER NOT NULL DEFAULT 0 CHECK (is_sensitive IN (0, 1)),
    validation_status TEXT NOT NULL DEFAULT 'pendente'
        CHECK (validation_status IN ('pendente', 'confirmado', 'divergente', 'nao_encontrado')),
    reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX document_field_mappings_document_idx ON document_field_mappings(document_id);
CREATE INDEX document_field_mappings_status_idx ON document_field_mappings(validation_status);

CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL CHECK (
        action IN ('criacao', 'alteracao', 'exclusao', 'consulta', 'importacao', 'login', 'logout')
    ),
    table_name TEXT NOT NULL,
    record_id INTEGER,
    field_name TEXT,
    old_value TEXT,
    new_value TEXT,
    sensitive_field INTEGER NOT NULL DEFAULT 0 CHECK (sensitive_field IN (0, 1)),
    company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT NOT NULL,
    CHECK (sensitive_field = 0 OR (old_value IS NULL AND new_value IS NULL))
);

CREATE INDEX audit_logs_user_idx ON audit_logs(user_id);
CREATE INDEX audit_logs_company_idx ON audit_logs(company_id);
CREATE INDEX audit_logs_action_idx ON audit_logs(action);
CREATE INDEX audit_logs_created_at_idx ON audit_logs(created_at);
CREATE INDEX audit_logs_table_record_idx ON audit_logs(table_name, record_id);

CREATE TABLE login_attempts (
    email TEXT NOT NULL COLLATE NOCASE,
    ip_address TEXT NOT NULL,
    failed_attempts INTEGER NOT NULL DEFAULT 0 CHECK (failed_attempts >= 0),
    first_failed_at TEXT NOT NULL,
    last_failed_at TEXT NOT NULL,
    PRIMARY KEY (email, ip_address)
);
