CREATE TABLE newsic_instances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    server_name TEXT NOT NULL CHECK (length(trim(server_name)) BETWEEN 2 AND 160),
    internal_ip TEXT,
    external_ip TEXT,
    web_port TEXT,
    web_username TEXT,
    web_password_encrypted TEXT,
    ssh_port TEXT,
    ssh_username TEXT,
    ssh_password_encrypted TEXT,
    server_type TEXT CHECK (server_type IN ('virtual', 'cloud', 'fisico')),
    freemyip_link TEXT,
    software_version TEXT,
    documentation_path TEXT,
    status TEXT NOT NULL DEFAULT 'ativo'
        CHECK (status IN ('ativo', 'inativo', 'manutencao')),
    notes TEXT,
    source_document_id INTEGER REFERENCES documents(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX newsic_instances_one_active_per_company_idx
    ON newsic_instances(company_id)
    WHERE status = 'ativo';
CREATE INDEX newsic_instances_company_idx ON newsic_instances(company_id);
CREATE INDEX newsic_instances_status_idx ON newsic_instances(status);

CREATE TABLE newsic_extensions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newsic_instance_id INTEGER NOT NULL REFERENCES newsic_instances(id) ON DELETE CASCADE,
    extension_number TEXT NOT NULL,
    registration_password_encrypted TEXT,
    ip_address TEXT,
    user_name TEXT,
    department TEXT,
    call_recording INTEGER NOT NULL DEFAULT 0 CHECK (call_recording IN (0, 1)),
    permission_lof INTEGER NOT NULL DEFAULT 0 CHECK (permission_lof IN (0, 1)),
    permission_loc INTEGER NOT NULL DEFAULT 0 CHECK (permission_loc IN (0, 1)),
    permission_ddf INTEGER NOT NULL DEFAULT 0 CHECK (permission_ddf IN (0, 1)),
    permission_ddc INTEGER NOT NULL DEFAULT 0 CHECK (permission_ddc IN (0, 1)),
    permission_ddi INTEGER NOT NULL DEFAULT 0 CHECK (permission_ddi IN (0, 1)),
    permission_srv INTEGER NOT NULL DEFAULT 0 CHECK (permission_srv IN (0, 1)),
    status TEXT NOT NULL DEFAULT 'ativo'
        CHECK (status IN ('ativo', 'inativo', 'manutencao')),
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (newsic_instance_id, extension_number)
);

CREATE INDEX newsic_extensions_instance_idx ON newsic_extensions(newsic_instance_id);
CREATE INDEX newsic_extensions_status_idx ON newsic_extensions(status);

CREATE TABLE newsic_trunks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newsic_instance_id INTEGER NOT NULL REFERENCES newsic_instances(id) ON DELETE CASCADE,
    pilot_number TEXT NOT NULL,
    trunk_type TEXT CHECK (trunk_type IN ('analogico', 'sip', 'sip_trunk', 'voip', 'outro')),
    carrier TEXT,
    receives_calls INTEGER CHECK (receives_calls IN (0, 1)),
    channel_quantity INTEGER CHECK (channel_quantity IS NULL OR channel_quantity >= 0),
    ddrs TEXT,
    status TEXT NOT NULL DEFAULT 'ativo'
        CHECK (status IN ('ativo', 'inativo', 'manutencao')),
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newsic_trunks_instance_idx ON newsic_trunks(newsic_instance_id);
CREATE INDEX newsic_trunks_status_idx ON newsic_trunks(status);

CREATE TABLE newsic_ura_options (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newsic_instance_id INTEGER NOT NULL REFERENCES newsic_instances(id) ON DELETE CASCADE,
    ura_type TEXT,
    option TEXT,
    schedule TEXT,
    recording TEXT,
    destination TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newsic_ura_options_instance_idx ON newsic_ura_options(newsic_instance_id);

CREATE TABLE newsic_routings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newsic_instance_id INTEGER NOT NULL REFERENCES newsic_instances(id) ON DELETE CASCADE,
    ipo TEXT,
    number TEXT,
    name TEXT,
    forwarding TEXT,
    recording TEXT,
    destination TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newsic_routings_instance_idx ON newsic_routings(newsic_instance_id);

CREATE TABLE newsic_networks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newsic_instance_id INTEGER NOT NULL REFERENCES newsic_instances(id) ON DELETE CASCADE,
    gateway TEXT,
    network_type TEXT,
    interface_ip TEXT,
    subnet_mask TEXT,
    dns TEXT,
    proxy_ip TEXT,
    proxy_port TEXT,
    proxy_type TEXT,
    audio_ip TEXT,
    audio_subnet_mask TEXT,
    audio_gateway TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newsic_networks_instance_idx ON newsic_networks(newsic_instance_id);
