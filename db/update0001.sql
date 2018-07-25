-- Redirection from a page id to a target page id
-- saved by the user in the admin page
CREATE TABLE REDIRECTIONS (
    SOURCE       TEXT CONSTRAINT REDIRECTION_PK PRIMARY KEY, -- Source page id
    TARGET       TEXT -- Target Page id
);

-- Log of the redirections
CREATE TABLE REDIRECTIONS_LOG (
  TIMESTAMP    TIMESTAMP,
  SOURCE       TEXT,
  TARGET       TEXT,
  TYPE         TEXT, -- which algorithm or manual entry
  REFERRER     TEXT
);


-- Table redirection cache
-- This table can be make empty
CREATE TABLE REDIRECTION_CACHE (
  SOURCE       TEXT CONSTRAINT REDIRECTION_PK PRIMARY KEY, -- Source page id
  TARGET       TEXT, -- target page id
  TYPE         TEXT -- The algo
);




