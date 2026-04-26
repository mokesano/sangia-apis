-- Wizdam Indonesia — Database Schema (PostgreSQL 14+)
-- Run: psql -U postgres -f database/schema.pgsql.sql
-- Auto-applied on first boot via src/Config/Migrator.php

CREATE TABLE IF NOT EXISTS users (
    id         SERIAL       PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL UNIQUE,
    password   VARCHAR(255) DEFAULT NULL,
    orcid_id   VARCHAR(50)  DEFAULT NULL UNIQUE,
    role       VARCHAR(20)  NOT NULL DEFAULT 'viewer' CHECK (role IN ('admin','researcher','viewer')),
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS institutions (
    id                  SERIAL        PRIMARY KEY,
    name                VARCHAR(255)  NOT NULL,
    short_name          VARCHAR(50)   DEFAULT NULL,
    type                VARCHAR(100)  NOT NULL DEFAULT 'universitas',
    province            VARCHAR(100)  DEFAULT NULL,
    city                VARCHAR(100)  DEFAULT NULL,
    latitude            NUMERIC(10,8) DEFAULT NULL,
    longitude           NUMERIC(11,8) DEFAULT NULL,
    website             VARCHAR(255)  DEFAULT NULL,
    logo_url            VARCHAR(255)  DEFAULT NULL,
    total_researchers   INT           NOT NULL DEFAULT 0,
    total_publications  INT           NOT NULL DEFAULT 0,
    wizdam_score        NUMERIC(5,2)  NOT NULL DEFAULT 0,
    created_at          TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_inst_province ON institutions (province);
CREATE INDEX IF NOT EXISTS idx_inst_score    ON institutions (wizdam_score);

CREATE TABLE IF NOT EXISTS researchers (
    id                  SERIAL       PRIMARY KEY,
    orcid_id            VARCHAR(50)  DEFAULT NULL UNIQUE,
    full_name           VARCHAR(255) NOT NULL,
    bio                 TEXT         DEFAULT NULL,
    position_title      VARCHAR(100) DEFAULT NULL,
    department          VARCHAR(150) DEFAULT NULL,
    institution_id      INT          REFERENCES institutions(id) ON DELETE SET NULL,
    province            VARCHAR(100) DEFAULT NULL,
    city                VARCHAR(100) DEFAULT NULL,
    field_of_study      JSONB        DEFAULT '[]',
    expertise_tags      JSONB        DEFAULT '[]',
    sdgs_primary_goals  JSONB        DEFAULT '[]',
    total_publications  INT          NOT NULL DEFAULT 0,
    total_citations     INT          NOT NULL DEFAULT 0,
    h_index             SMALLINT     NOT NULL DEFAULT 0,
    i10_index           SMALLINT     NOT NULL DEFAULT 0,
    wizdam_score        NUMERIC(5,2) NOT NULL DEFAULT 0,
    wizdam_percentile   NUMERIC(5,2) NOT NULL DEFAULT 0,
    profile_image_url   VARCHAR(255) DEFAULT NULL,
    website             VARCHAR(255) DEFAULT NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_researchers_prov  ON researchers (province);
CREATE INDEX IF NOT EXISTS idx_researchers_score ON researchers (wizdam_score);
CREATE INDEX IF NOT EXISTS idx_researchers_inst  ON researchers (institution_id);

CREATE TABLE IF NOT EXISTS publications (
    id                  SERIAL       PRIMARY KEY,
    doi                 VARCHAR(255) DEFAULT NULL UNIQUE,
    title               TEXT         NOT NULL,
    abstract            TEXT         DEFAULT NULL,
    authors_list        TEXT         DEFAULT NULL,
    journal_title       VARCHAR(255) DEFAULT NULL,
    publication_year    SMALLINT     DEFAULT NULL,
    cited_by_count      INT          NOT NULL DEFAULT 0,
    wizdam_score        NUMERIC(5,2) NOT NULL DEFAULT 0,
    sdgs_goals          JSONB        DEFAULT '[]',
    document_type       VARCHAR(50)  NOT NULL DEFAULT 'article',
    access_type         VARCHAR(50)  NOT NULL DEFAULT 'open_access',
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pub_year  ON publications (publication_year);
CREATE INDEX IF NOT EXISTS idx_pub_score ON publications (wizdam_score);
CREATE INDEX IF NOT EXISTS idx_pub_cites ON publications (cited_by_count);

CREATE TABLE IF NOT EXISTS researcher_publications (
    researcher_id  INT NOT NULL REFERENCES researchers  (id) ON DELETE CASCADE,
    publication_id INT NOT NULL REFERENCES publications (id) ON DELETE CASCADE,
    PRIMARY KEY (researcher_id, publication_id)
);

CREATE TABLE IF NOT EXISTS impact_scores (
    id              SERIAL      PRIMARY KEY,
    entity_type     VARCHAR(20) NOT NULL CHECK (entity_type IN ('researcher','article','institution','journal')),
    entity_id       INT         NOT NULL,
    composite_score NUMERIC(5,2) NOT NULL DEFAULT 0,
    pillar_academic NUMERIC(5,2) NOT NULL DEFAULT 0,
    pillar_social   NUMERIC(5,2) NOT NULL DEFAULT 0,
    pillar_economic NUMERIC(5,2) NOT NULL DEFAULT 0,
    pillar_sdg      NUMERIC(5,2) NOT NULL DEFAULT 0,
    sdg_tags        JSONB        DEFAULT '[]',
    calculated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_is_entity     ON impact_scores (entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_is_calculated ON impact_scores (calculated_at);

CREATE TABLE IF NOT EXISTS jobs (
    job_id        VARCHAR(50)  PRIMARY KEY,
    class         VARCHAR(100) DEFAULT NULL,
    payload       JSONB        DEFAULT NULL,
    status        VARCHAR(20)  NOT NULL DEFAULT 'pending'
                               CHECK (status IN ('pending','processing','completed','failed')),
    priority      SMALLINT     NOT NULL DEFAULT 0,
    available_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    started_at    TIMESTAMPTZ  DEFAULT NULL,
    completed_at  TIMESTAMPTZ  DEFAULT NULL,
    failed_at     TIMESTAMPTZ  DEFAULT NULL,
    attempts      SMALLINT     NOT NULL DEFAULT 0,
    max_attempts  SMALLINT     NOT NULL DEFAULT 3,
    error         TEXT         DEFAULT NULL,
    result        JSONB        DEFAULT NULL,
    progress      SMALLINT     NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs (status);
CREATE INDEX IF NOT EXISTS idx_jobs_avail  ON jobs (available_at);
