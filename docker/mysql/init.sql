/* Filename: D:\projects\advanced-todo-app\docker\mysql\init.sql */

/* Create the main database if it doesn't exist */
CREATE DATABASE IF NOT EXISTS todo_app;

/* You can grant privileges here, but the docker-compose.yml already */
/* creates the user 'devuser' with full access to 'todo_app'. */