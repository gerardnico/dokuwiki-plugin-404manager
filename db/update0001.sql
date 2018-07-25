-- Redirection from a page id to a target page id
CREATE TABLE REDIRECTIONS (
    SOURCE       TEXT CONSTRAINT REDIRECTION_PK PRIMARY KEY, -- A regular expression pattern (may be a full page id)
    TARGET       TEXT -- A target pattern substitution (may be a full page id)
);

-- Log of the redirections
CREATE TABLE REDIRECTIONS_LOG (
  TIMESTAMP    TIMESTAMP,
  SOURCE       TEXT,
  TARGET       TEXT,
  REFERRER     TEXT,
  TYPE         TEXT
);


-- Table redirection cache
-- This table can be make empty
CREATE TABLE REDIRECTION_CACHE (
  SOURCE       TEXT CONSTRAINT REDIRECTION_PK PRIMARY KEY, -- A regular expression pattern (may be a full page id)
  TARGET       TEXT -- A target pattern substitution (may be a full page id)
);




