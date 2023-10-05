#!/bin/bash
echo -n "INSERT IGNORE INTO members (voting_id, skymanager_id, name, username, email) VALUES "
paste -d '' <(seq 100 999 | shuf | head -n $(wc -l < voters.json) | sed 's/^/(/') <(jq -r '", " + (.smid|tostring) + ", \"" + (.name // .username) + "\", \"" + .username + "\", " + (if .email then ("\"" + .email + "\"") else "NULL" end) + ")"' voters.json) | tr '\n' ',' | sed 's/,$/;\n/'
