const request = require('supertest');
const app = require('./app');

describe('GET /', () => {
  it('should return 200 when not logged in', async () => {
    const res = await request(app).get('/');
    expect(res.statusCode).toBe(200);
  });
});

describe('GET /login', () => {
  it('should return 200 when not logged in', async () => {
    const res = await request(app).get('/login');
    expect(res.statusCode).toBe(200);
  });
});

describe('POST /login', () => {
  it('should return 500 without a valid input', async () => {
    const res = await request(app).post('/login');
    expect(res.statusCode).toBe(500);
  });
});

describe('GET /admin', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).get('/admin');
    expect(res.statusCode).toBe(302);
  });
});

describe('GET /admin/view-labtech', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).get('/admin/view-labtech');
    expect(res.statusCode).toBe(302);
  });
});

describe('GET /admin/add-labtech', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).get('/admin/add-labtech');
    expect(res.statusCode).toBe(302);
  });
});

describe('POST /admin/add-labtech', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).post('/admin/add-labtech');
    expect(res.statusCode).toBe(302);
  });
});

describe('GET /admin/remove-labtech', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).get('/admin/remove-labtech');
    expect(res.statusCode).toBe(302);
  });
});

describe('POST /admin/remove-labtech', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).post('/admin/remove-labtech');
    expect(res.statusCode).toBe(302);
  });
});

describe('GET /logout', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).get('/logout');
    expect(res.statusCode).toBe(302);
  });
});

describe('GET /register', () => {
  it('should return 200 when not logged in', async () => {
    const res = await request(app).get('/register');
    expect(res.statusCode).toBe(200);
  });
});

describe('POST /register', () => {
  it('should return 500 without a valid input', async () => {
    const res = await request(app).post('/register');
    expect(res.statusCode).toBe(500);
  });
});

describe('GET /dashboard/student', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).get('/dashboard/student');
    expect(res.statusCode).toBe(302);
  });
});

describe('GET /dashboard/technician', () => {
  it('should return 302 when not logged in', async () => {
    const res = await request(app).get('/dashboard/technician');
    expect(res.statusCode).toBe(302);
  });
});