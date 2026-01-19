Let’s build a full-scale school-service monitoring system REST API.  
Stack: PHP 8.x (no frameworks), Vanilla MySQL 8.x, JSON-only REST, JWT auth, prepared statements everywhere.

Entry flow  
1. Landing route GET / → returns { “hasAccount”: bool }.  
   - false → client redirects to POST /register  
   - true  → client redirects to POST /login  
2. POST /register → role ∈ {admin,student,driver,parent}.  
   On success → auto-login returns JWT + role.  
3. POST /login → email + password → JWT + role.  
JWT payload: { uuid, role, iat, exp }.  
All further requests: Header Authorization: Bearer <JWT>.

Role-based routes (prefix /api)

ADMIN  
GET  /admin/dashboard  
→ { drivers:int, students:int, parents:int }  
GET  /admin/users?role=driver|student|parent  
→ [ {uuid, name, email, role, created_at} ]  
POST /admin/users  
→ { role, name, email, password, …role-specific cols }  
PATCH /admin/users/:uuid  
DELETE /admin/users/:uuid  
GET  /admin/drivers/:uuid/location  
→ { lat, lng, updated_at } (student identities never exposed)  
POST /admin/assignments  
→ { driver_uuid, student_uuid }  
PATCH /admin/drivers/:uuid/limit  
→ { max_students:int }

STUDENT  
GET  /student/profile  
PATCH /student/profile  
GET  /student/driver  
→ { name, phone, code, chat_ws_url }  
POST /student/join  
→ { code } (driver’s share-code)  
→ 201 if room < driver.max_students  
POST /student/parents  
→ { parent_uuid } (creates link)  
GET  /student/parents  
WebSocket /chat/{driver_uuid} (JWT in sub-protocol)

DRIVER  
GET  /driver/profile  
PATCH /driver/profile  
GET  /driver/code  
→ { code:string } (auto-generated 6-digit, unique)  
POST /driver/location  
→ { lat, lng }  
GET  /driver/students  
→ [ {uuid, name, anon_id} ] (anon_id = hash, no real names)  
WebSocket /chat (same endpoint as student)

PARENT  
GET  /parent/profile  
PATCH /parent/profile  
GET  /parent/children  
→ [ {uuid, name, driver_name, last_location} ]  
GET  /parent/children/:uuid/location  
→ { lat, lng, updated_at } (location is driver’s, child anonymised)

DB schema (InnoDB, utf8mb4)

users  
- uuid BINARY(16) PK  
- role ENUM('admin','student','driver','parent')  
- name VARCHAR(100)  
- email VARCHAR(255) UNIQUE  
- password_hash VARCHAR(255)  
- created_at TIMESTAMP  

drivers  
- user_uuid BINARY(16) PK/FK users.uuid CASCADE  
- max_students TINYINT DEFAULT 7  
- code CHAR(6) UNIQUE  
- lat DECIMAL(10,8) NULL  
- lng DECIMAL(11,8) NULL  
- location_updated TIMESTAMP NULL  

students  
- user_uuid BINARY(16) PK/FK users.uuid CASCADE  
- driver_uuid BINARY(16) NULL FK drivers.user_uuid  
- anon_id CHAR(8) UNIQUE (random)  

parents  
- user_uuid BINARY(16) PK/FK users.uuid CASCADE  

student_parents (link)  
- student_uuid BINARY(16) FK students.user_uuid  
- parent_uuid  BINARY(16) FK parents.user_uuid  
PK(student_uuid, parent_uuid)

JWT secret stored in environment variable.  
All passwords hashed with password_hash() (bcrypt).  
Every query uses prepared statements; no raw concatenation.  
Return standard HTTP codes + JSON {message, data, error}.