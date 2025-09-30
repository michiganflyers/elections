#!/bin/bash
echo -n "INSERT INTO members (voting_id, skymanager_id, name, username, email) VALUES "
paste -d '' <(seq 100 999 | shuf | head -n $(wc -l < voters.json) | sed 's/^/(/') <(jq -r "\", \" + (.smid|tostring) + \", '\" + (.name // .username) + \"', '\" + .username + \"', \" + (if .email then (\"'\" + .email + \"'\") else \"NULL\" end) + \")\"" voters.json) | tr '\n' ',' | sed 's/,$/ /'
echo 'ON CONFLICT (skymanager_id) DO UPDATE SET voting_id = EXCLUDED.voting_id;'
