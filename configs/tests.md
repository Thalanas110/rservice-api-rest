# API Testing Guide & JSON Payloads

This guide contains the **EXACT** JSONs you need for Postman. No guessing!

**Base URL:** `http://localhost/OOPapi2/public`

---

## 1. Registration (Create Users)

**Endpoint:** `POST /register`

### A. Register an ADMIN
*Copy and paste this into Body -> raw -> JSON:*
```json
{
    "role": "admin",
    "name": "Super Admin",
    "email": "admin@test.com",
    "password": "password123"
}
```

### B. Register a DRIVER
*Copy and paste this:*
```json
{
    "role": "driver",
    "name": "Bus Driver Bob",
    "email": "driver@test.com",
    "password": "password123",
    "max_students": 10
}
```

### C. Register a STUDENT
*Copy and paste this:*
```json
{
    "role": "student",
    "name": "Timmy Student",
    "email": "student@test.com",
    "password": "password123"
}
```

### D. Register a PARENT
*Copy and paste this:*
```json
{
    "role": "parent",
    "name": "Momma Bear",
    "email": "parent@test.com",
    "password": "password123"
}
```

---

## 2. Login (Get Tokens)

**Endpoint:** `POST /login`

### Login Payload (Use for ALL accounts)
*Just change the email to match the user you are logging in as.*
```json
{
    "email": "admin@test.com",
    "password": "password123"
}
```

---

## 3. Admin Actions
*(Requires Admin Token)*

### Create User Manually
**Endpoint:** `POST /api/admin/users`
```json
{
    "role": "driver",
    "name": "New Driver",
    "email": "newdriver@test.com",
    "password": "password123",
    "max_students": 5
}
```

### Update User
**Endpoint:** `PATCH /api/admin/users/<UUID>`
```json
{
    "name": "Updated Name",
    "email": "updated@test.com"
}
```

### Assign Student to Driver
**Endpoint:** `POST /api/admin/assignments`
```json
{
    "driver_uuid": "INSERT_DRIVER_UUID_HERE",
    "student_uuid": "INSERT_STUDENT_UUID_HERE"
}
```

### Update Driver Limit
**Endpoint:** `PATCH /api/admin/drivers/<UUID>/limit`
```json
{
    "max_students": 15
}
```

---

## 4. Driver Actions
*(Requires Driver Token)*

### Update Location
**Endpoint:** `POST /api/driver/location`
```json
{
    "lat": 14.5547,
    "lng": 121.0244
}
```

---

## 5. Student Actions
*(Requires Student Token)*

### Join a Driver
**Endpoint:** `POST /api/student/join`
```json
{
    "code": "123456"
}
```
*(Note: Replace "123456" with the actual code from the Driver's profile)*

### Link a Parent
**Endpoint:** `POST /api/student/parents`
```json
{
    "parent_uuid": "INSERT_PARENT_UUID_HERE"
}
```

---

## 6. Negative Tests (Try to Break It)

### Invalid Login
**Endpoint:** `POST /login`
```json
{
    "email": "admin@test.com",
    "password": "WRONG_PASSWORD"
}
```

### Register Duplicate Email
**Endpoint:** `POST /register`
*Send the exact same JSON you used in Step 1A twice.*

### Invalid Role Register
**Endpoint:** `POST /register`
```json
{
    "role": "hacker",
    "name": "Bad Guy",
    "email": "hacker@test.com",
    "password": "123"
}
```

### Driver Update Location (Missing Data)
**Endpoint:** `POST /api/driver/location`
```json
{
    "lat": 14.5547
}
```
*(Missing "lng")*
