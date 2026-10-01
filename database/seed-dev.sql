-- Optional sample rows for local development (XAMPP). Import after schema.sql.
INSERT INTO messages (name, email, subject, body, project, ip_hash)
VALUES ('Sample Recruiter', 'recruiter@example.com', 'Hiring enquiry',
        'Hi! We have a backend role that looks like a great fit.', NULL, REPEAT('0', 64));

INSERT INTO tip_ledger (tip_id, event, amount, phone_hash, phone_last3, checkout_request_id, source, environment, raw_payload)
VALUES (REPEAT('a', 32), 'REQUESTED', 100, REPEAT('0', 64), '678', NULL, 'app', 'fake', NULL),
       (REPEAT('a', 32), 'SENT', 100, REPEAT('0', 64), '678', 'ws_CO_SAMPLE_0001', 'app', 'fake', NULL);
INSERT INTO tip_ledger (tip_id, event, amount, phone_hash, phone_last3, checkout_request_id, mpesa_receipt, result_code, result_desc, source, environment, dedupe_key, raw_payload)
VALUES (REPEAT('a', 32), 'COMPLETED', 100, REPEAT('0', 64), '678', 'ws_CO_SAMPLE_0001', 'SAMPLE0001', 0,
        'The service request is processed successfully.', 'callback', 'fake', 'ws_CO_SAMPLE_0001:final', NULL);
