-- Newbot: campos do painel de gestao da instancia.
-- Somente ADD COLUMN e CREATE TABLE: nenhuma tabela e reconstruida e nenhum dado e apagado.

ALTER TABLE newbot_instances ADD COLUMN instance_code TEXT;
ALTER TABLE newbot_instances ADD COLUMN access_link TEXT;
CREATE INDEX newbot_instances_instance_code_idx ON newbot_instances(instance_code);

ALTER TABLE newbot_channels ADD COLUMN activation_time TEXT
    CHECK (
        activation_time IS NULL
        OR (
            length(activation_time) = 5
            AND activation_time GLOB '[0-2][0-9]:[0-5][0-9]'
            AND substr(activation_time, 1, 2) < '24'
        )
    );

ALTER TABLE newbot_administrators ADD COLUMN name TEXT;
ALTER TABLE newbot_administrators ADD COLUMN email TEXT;

ALTER TABLE newbot_integrations ADD COLUMN email TEXT;

CREATE TABLE newbot_supervisors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    newbot_instance_id INTEGER NOT NULL REFERENCES newbot_instances(id) ON DELETE CASCADE,
    name TEXT,
    login TEXT,
    email TEXT,
    password_encrypted TEXT,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX newbot_supervisors_instance_idx ON newbot_supervisors(newbot_instance_id);

-- Copia o supervisor que hoje vive dentro do administrador. Valores vazios viram NULL.
-- A senha do administrador nao e copiada: nao e a senha do supervisor.
-- supervisor_login e supervisor_email permanecem na tabela de origem.
INSERT INTO newbot_supervisors (newbot_instance_id, login, email, created_at, updated_at)
SELECT newbot_instance_id,
       NULLIF(TRIM(supervisor_login), ''),
       NULLIF(TRIM(supervisor_email), ''),
       created_at,
       updated_at
FROM newbot_administrators
WHERE TRIM(COALESCE(supervisor_login, '')) <> ''
   OR TRIM(COALESCE(supervisor_email, '')) <> ''
ORDER BY id;
