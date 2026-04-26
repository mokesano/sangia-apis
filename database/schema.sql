-- Wizdam Indonesia — Database Schema
-- Engine: MySQL 8.0+ / MariaDB 10.6+
-- Charset: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────
-- users
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name       VARCHAR(255)     NOT NULL,
    email      VARCHAR(255)     NOT NULL,
    password   VARCHAR(255)     DEFAULT NULL,
    orcid_id   VARCHAR(50)      DEFAULT NULL,
    role       ENUM('admin','researcher','viewer') NOT NULL DEFAULT 'viewer',
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email   (email),
    UNIQUE KEY uq_users_orcid   (orcid_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────
-- institutions
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS institutions (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name                VARCHAR(255)     NOT NULL,
    short_name          VARCHAR(50)      DEFAULT NULL,
    type                VARCHAR(100)     DEFAULT 'universitas',
    province            VARCHAR(100)     DEFAULT NULL,
    city                VARCHAR(100)     DEFAULT NULL,
    latitude            DECIMAL(10,8)    DEFAULT NULL,
    longitude           DECIMAL(11,8)    DEFAULT NULL,
    website             VARCHAR(255)     DEFAULT NULL,
    logo_url            VARCHAR(255)     DEFAULT NULL,
    total_researchers   INT UNSIGNED     NOT NULL DEFAULT 0,
    total_publications  INT UNSIGNED     NOT NULL DEFAULT 0,
    wizdam_score        DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
    created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_institutions_province (province),
    KEY idx_institutions_score    (wizdam_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────
-- researchers
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS researchers (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    orcid_id            VARCHAR(50)      DEFAULT NULL,
    full_name           VARCHAR(255)     NOT NULL,
    bio                 TEXT             DEFAULT NULL,
    position_title      VARCHAR(100)     DEFAULT NULL,
    department          VARCHAR(150)     DEFAULT NULL,
    institution_id      INT UNSIGNED     DEFAULT NULL,
    province            VARCHAR(100)     DEFAULT NULL,
    city                VARCHAR(100)     DEFAULT NULL,
    field_of_study      JSON             DEFAULT NULL,
    expertise_tags      JSON             DEFAULT NULL,
    sdgs_primary_goals  JSON             DEFAULT NULL,
    total_publications  INT UNSIGNED     NOT NULL DEFAULT 0,
    total_citations     INT UNSIGNED     NOT NULL DEFAULT 0,
    h_index             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    i10_index           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    wizdam_score        DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
    wizdam_percentile   DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
    profile_image_url   VARCHAR(255)     DEFAULT NULL,
    website             VARCHAR(255)     DEFAULT NULL,
    created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_researchers_orcid (orcid_id),
    KEY idx_researchers_institution (institution_id),
    KEY idx_researchers_province    (province),
    KEY idx_researchers_score       (wizdam_score),
    CONSTRAINT fk_researchers_institution
        FOREIGN KEY (institution_id) REFERENCES institutions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────
-- publications
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS publications (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    doi                 VARCHAR(255)     DEFAULT NULL,
    title               TEXT             NOT NULL,
    abstract            LONGTEXT         DEFAULT NULL,
    authors_list        TEXT             DEFAULT NULL,
    journal_title       VARCHAR(255)     DEFAULT NULL,
    publication_year    SMALLINT UNSIGNED DEFAULT NULL,
    cited_by_count      INT UNSIGNED     NOT NULL DEFAULT 0,
    wizdam_score        DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
    sdgs_goals          JSON             DEFAULT NULL,
    document_type       VARCHAR(50)      NOT NULL DEFAULT 'article',
    access_type         VARCHAR(50)      NOT NULL DEFAULT 'open_access',
    created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_publications_doi  (doi),
    KEY idx_publications_year       (publication_year),
    KEY idx_publications_score      (wizdam_score),
    KEY idx_publications_citations  (cited_by_count),
    FULLTEXT KEY ft_publications_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────
-- researcher_publications (junction)
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS researcher_publications (
    researcher_id   INT UNSIGNED NOT NULL,
    publication_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (researcher_id, publication_id),
    KEY idx_rp_publication (publication_id),
    CONSTRAINT fk_rp_researcher
        FOREIGN KEY (researcher_id)  REFERENCES researchers  (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_publication
        FOREIGN KEY (publication_id) REFERENCES publications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────
-- impact_scores
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS impact_scores (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    entity_type     ENUM('researcher','article','institution','journal') NOT NULL,
    entity_id       INT UNSIGNED  NOT NULL,
    composite_score DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    pillar_academic DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    pillar_social   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    pillar_economic DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    pillar_sdg      DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    sdg_tags        JSON          DEFAULT NULL,
    calculated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_is_entity      (entity_type, entity_id),
    KEY idx_is_calculated  (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────
-- jobs
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jobs (
    job_id        VARCHAR(50)  NOT NULL,
    class         VARCHAR(100) DEFAULT NULL,
    payload       JSON         DEFAULT NULL,
    status        ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    priority      TINYINT      NOT NULL DEFAULT 0,
    available_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at    TIMESTAMP    NULL DEFAULT NULL,
    completed_at  TIMESTAMP    NULL DEFAULT NULL,
    failed_at     TIMESTAMP    NULL DEFAULT NULL,
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 3,
    error         TEXT         DEFAULT NULL,
    result        JSON         DEFAULT NULL,
    progress      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (job_id),
    KEY idx_jobs_status   (status),
    KEY idx_jobs_avail    (available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
