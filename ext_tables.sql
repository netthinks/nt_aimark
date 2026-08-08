#
# Media assets — AI transparency declaration
#
CREATE TABLE sys_file_metadata (
	tx_ntaimark_status tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_disclosure tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_exempt_reason varchar(32) DEFAULT '' NOT NULL,
	tx_ntaimark_icon varchar(16) DEFAULT '' NOT NULL,
	tx_ntaimark_label_text varchar(255) DEFAULT '' NOT NULL,
	tx_ntaimark_system varchar(128) DEFAULT '' NOT NULL,
	tx_ntaimark_vendor varchar(128) DEFAULT '' NOT NULL,
	tx_ntaimark_prompt text,
	tx_ntaimark_created_at int(11) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_reviewer int(11) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_reviewed_at int(11) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_c2pa_state tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_c2pa_manifest text,
	tx_ntaimark_source_type varchar(255) DEFAULT '' NOT NULL,
	tx_ntaimark_notes text,

	KEY tx_ntaimark_open (tx_ntaimark_status)
);

#
# Texts — the operator obligation for content on matters of public interest
#
CREATE TABLE pages (
	tx_ntaimark_text_status tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_public_interest tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_editorial_control tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_responsible varchar(255) DEFAULT '' NOT NULL
);

CREATE TABLE tt_content (
	tx_ntaimark_text_status tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_public_interest tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_editorial_control tinyint(1) unsigned DEFAULT 0 NOT NULL,
	tx_ntaimark_responsible varchar(255) DEFAULT '' NOT NULL
);

#
# Append-only audit trail. The application never updates or deletes rows here —
# the record has to stay readable as evidence.
#
CREATE TABLE tx_ntaimark_audit (
	pid int(11) DEFAULT 0 NOT NULL,
	tstamp int(11) unsigned DEFAULT 0 NOT NULL,
	table_name varchar(64) DEFAULT '' NOT NULL,
	record_uid int(11) unsigned DEFAULT 0 NOT NULL,
	be_user int(11) unsigned DEFAULT 0 NOT NULL,
	# Denormalised on purpose: the evidence must survive deletion of the backend user.
	be_user_name varchar(255) DEFAULT '' NOT NULL,
	action varchar(32) DEFAULT '' NOT NULL,
	field_name varchar(64) DEFAULT '' NOT NULL,
	old_value text,
	new_value text,
	source varchar(32) DEFAULT '' NOT NULL,

	KEY record (table_name, record_uid),
	KEY tstamp (tstamp)
);
