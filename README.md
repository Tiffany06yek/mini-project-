Database Guidelines
1. Project Database Structure

Data Source: All table structures must strictly follow the latest Data Schema (ERD).
Primary Keys: Every table uses id as the primary key.
Foreign Keys: Foreign key columns are named using the format table_id. 
Examples:
orders.buyer_id → users.id
orders.merchant_id → merchants.id

Field Types: Must match exactly with the referenced table. For example:
If users.id is BIGINT UNSIGNED, then orders.buyer_id must also be BIGINT UNSIGNED.
If merchants.id is INT UNSIGNED, then orders.merchant_id must also be INT UNSIGNED.

2. File Organization

db/migrations/ → Contains database structure SQL files (CREATE/ALTER TABLE).
Naming convention: description.sql
Example: create_orders.sql

db/seeds/ → Contains initial or test data SQL files (INSERT statements).
Naming convention: seed_tablename.sql
Example: seed_merchants.sql


3. Collaboration Rules

Each member creates their own migration file. Do not edit the same file together.
Always run git pull before committing to avoid merge conflicts.
All changes must align with the Data Schema. If you add extra fields, keep them but also record them for later schema updates.
Document retained fields in the README so the whole team knows what was added beyond the schema.
