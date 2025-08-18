CREATE USER 'replica_user'@'%' IDENTIFIED BY 'replica_secret';
GRANT REPLICATION SLAVE ON *.* TO 'replica_user'@'%';
FLUSH PRIVILEGES;