-- This script is executed when the 'db' container is first initialized.
-- It creates the replication user with the 'mysql_native_password' authentication plugin,
-- which is necessary for the replica to connect over an unencrypted Docker network.

-- Create the user. The environment variables are automatically substituted by the MySQL entrypoint.
CREATE USER '${MYSQL_REPLICATION_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${MYSQL_REPLICATION_PASSWORD}';

-- Grant the necessary privileges for replication.
GRANT REPLICATION SLAVE ON *.* TO '${MYSQL_REPLICATION_USER}'@'%';

-- Apply the changes.
FLUSH PRIVILEGES;
