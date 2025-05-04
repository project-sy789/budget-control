const express = require("express");
const cors = require("cors");
const path = require("path");
const { OAuth2Client } = require("google-auth-library");
const { v4: uuidv4 } = require("uuid");

const app = express();
const PORT = process.env.PORT || 3001;

app.use(cors());
app.use(express.json());

// --- In-memory Database (for demonstration) --- //
let projects = [];
let transactions = [];
let users = {
  // Initial admin user (will be updated/confirmed on first login)
  "admin_placeholder_uid": {
    uid: "admin_placeholder_uid",
    email: "nutrawee@subyaischool.ac.th",
    displayName: "Admin User",
    photoURL: "",
    role: "admin",
    approved: true,
  }
};

// --- Helper Functions --- //
const findUserByEmail = (email) => {
  return Object.values(users).find(user => user.email === email);
};

// --- API Routes --- //

// Authentication
const CLIENT_ID = process.env.GOOGLE_CLIENT_ID || "1068541751492-j1g5a8np8shnd4tnfkmp2bnpfdolam0m.apps.googleusercontent.com";
const client = new OAuth2Client(CLIENT_ID);

app.post("/api/auth/verify-token", async (req, res) => {
  try {
    const { token } = req.body;
    if (!token) {
      return res.status(400).json({ success: false, message: "ไม่พบ token" });
    }
    const ticket = await client.verifyIdToken({
      idToken: token,
      audience: CLIENT_ID,
    });
    const payload = ticket.getPayload();
    const userId = payload["sub"];
    const email = payload["email"];
    const displayName = payload["name"];
    const picture = payload["picture"];

    let user = users[userId];
    let isNewUser = false;

    if (!user) {
      // Check if user exists with this email but different UID (e.g., placeholder admin)
      const existingUserWithEmail = findUserByEmail(email);
      if (existingUserWithEmail && existingUserWithEmail.uid === "admin_placeholder_uid") {
          // Update the placeholder admin with the actual UID from Google
          console.log(`Updating placeholder admin ${existingUserWithEmail.email} to UID ${userId}`);
          delete users[existingUserWithEmail.uid]; // Remove placeholder
          user = { ...existingUserWithEmail, uid: userId, displayName, photoURL: picture };
          users[userId] = user;
      } else {
          // Create new user
          isNewUser = true;
          user = {
            uid: userId,
            email,
            displayName,
            photoURL: picture,
            role: "user", // Default role
            approved: false, // Default approval status
          };
          // Special case for the admin email
          if (email === "nutrawee@subyaischool.ac.th") {
            user.role = "admin";
            user.approved = true;
          }
          users[userId] = user;
          console.log("New user added:", user);
      }
    } else {
      // Update existing user's info if changed
      let updated = false;
      if (user.displayName !== displayName) { user.displayName = displayName; updated = true; }
      if (user.photoURL !== picture) { user.photoURL = picture; updated = true; }
      // Ensure admin role and approval persist for the admin email
      if (email === "nutrawee@subyaischool.ac.th") {
          if (user.role !== "admin") { user.role = "admin"; updated = true; }
          if (!user.approved) { user.approved = true; updated = true; }
      }
      if (updated) {
          console.log("User info updated:", user);
      }
    }

    res.json({ success: true, user });

  } catch (error) {
    console.error("เกิดข้อผิดพลาดในการตรวจสอบ token:", error);
    res.status(401).json({ success: false, message: "Token ไม่ถูกต้อง", error: error.message });
  }
});

// User Management API
app.get("/api/users", (req, res) => {
  // Simple authorization: Only allow admins to get all users (implement proper middleware later)
  // For now, we assume the frontend handles authorization checks before calling this
  console.log("GET /api/users - Returning all users");
  res.json(Object.values(users));
});

app.put("/api/users/:uid/role", (req, res) => {
  const { uid } = req.params;
  const { role } = req.body;

  if (!users[uid]) {
    return res.status(404).json({ success: false, message: "ไม่พบผู้ใช้" });
  }
  if (!["admin", "user"].includes(role)) {
    return res.status(400).json({ success: false, message: "บทบาทไม่ถูกต้อง" });
  }
  // Prevent changing the role of the primary admin
  if (users[uid].email === "nutrawee@subyaischool.ac.th" && role !== "admin") {
      return res.status(403).json({ success: false, message: "ไม่สามารถเปลี่ยนบทบาทของแอดมินหลักได้" });
  }

  users[uid].role = role;
  console.log(`PUT /api/users/:uid/role - Updated role for ${uid} to ${role}`);
  res.json(users[uid]);
});

app.put("/api/users/:uid/approve", (req, res) => {
  const { uid } = req.params;
  const { approved } = req.body; // Expecting { approved: true }

  if (!users[uid]) {
    return res.status(404).json({ success: false, message: "ไม่พบผู้ใช้" });
  }
  if (typeof approved !== 'boolean') {
      return res.status(400).json({ success: false, message: "สถานะการอนุมัติไม่ถูกต้อง" });
  }

  users[uid].approved = approved;
  console.log(`PUT /api/users/:uid/approve - Set approval for ${uid} to ${approved}`);
  res.json(users[uid]);
});

app.delete("/api/users/:uid", (req, res) => {
  const { uid } = req.params;

  if (!users[uid]) {
    return res.status(404).json({ success: false, message: "ไม่พบผู้ใช้" });
  }
  // Prevent deleting the primary admin
  if (users[uid].email === "nutrawee@subyaischool.ac.th") {
      return res.status(403).json({ success: false, message: "ไม่สามารถลบแอดมินหลักได้" });
  }

  delete users[uid];
  console.log(`DELETE /api/users/:uid - Deleted user ${uid}`);
  res.status(204).send();
});


// Projects API
app.get("/api/projects", (req, res) => {
  console.log("GET /api/projects - Returning:", projects);
  res.json(projects.sort((a, b) => new Date(b.startDate) - new Date(a.startDate))); // Sort by date descending
});

app.post("/api/projects", (req, res) => {
  const newProjectData = req.body;
  // Basic validation
  if (!newProjectData.name || !newProjectData.startDate || !newProjectData.endDate) {
      return res.status(400).json({ success: false, message: "ข้อมูลโครงการไม่ครบถ้วน (ชื่อ, วันเริ่ม, วันสิ้นสุด)" });
  }
  // Check for duplicate name (case-insensitive)
  const isDuplicate = projects.some(p => p.name.trim().toLowerCase() === newProjectData.name.trim().toLowerCase());
  if (isDuplicate) {
      return res.status(400).json({ success: false, message: `โครงการชื่อ "${newProjectData.name}" มีอยู่ในระบบแล้ว` });
  }
  const newProject = { ...newProjectData, id: uuidv4() };
  projects.push(newProject);
  console.log("POST /api/projects - Added:", newProject);
  res.status(201).json(newProject);
});

app.put("/api/projects/:id", (req, res) => {
  const { id } = req.params;
  const updatedProjectData = req.body;
  const projectIndex = projects.findIndex((p) => p.id === id);

  if (projectIndex === -1) {
    return res.status(404).json({ success: false, message: "ไม่พบโครงการ" });
  }

  // Update project data (excluding id)
  projects[projectIndex] = { ...projects[projectIndex], ...updatedProjectData, id: id };
  console.log("PUT /api/projects/:id - Updated:", projects[projectIndex]);
  res.json(projects[projectIndex]);
});

app.delete("/api/projects/:id", (req, res) => {
  const { id } = req.params;
  const initialProjectLength = projects.length;
  projects = projects.filter((p) => p.id !== id);

  if (projects.length === initialProjectLength) {
    return res.status(404).json({ success: false, message: "ไม่พบโครงการ" });
  }

  // Also delete associated transactions
  const initialTransactionLength = transactions.length;
  transactions = transactions.filter((t) => t.projectId !== id);
  console.log(`DELETE /api/projects/:id - Deleted project ${id} and ${initialTransactionLength - transactions.length} transactions`);

  res.status(204).send(); // No content
});

// Transactions API
app.get("/api/transactions", (req, res) => {
  const { projectId } = req.query;
  let result = transactions;
  if (projectId) {
    result = transactions.filter(t => t.projectId === projectId);
  }
  console.log(`GET /api/transactions ${projectId ? `(projectId: ${projectId})` : ''} - Returning ${result.length} transactions`);
  res.json(result.sort((a, b) => new Date(b.date) - new Date(a.date))); // Sort by date descending
});

app.post("/api/transactions", (req, res) => {
  const newTransactionData = req.body;
  // Basic validation
  if (!newTransactionData.projectId || !newTransactionData.description || newTransactionData.amount === undefined || !newTransactionData.date) {
      return res.status(400).json({ success: false, message: "ข้อมูลธุรกรรมไม่ครบถ้วน (ID โครงการ, คำอธิบาย, จำนวนเงิน, วันที่)" });
  }
  // Check if project exists
  if (!projects.some(p => p.id === newTransactionData.projectId)) {
      return res.status(400).json({ success: false, message: `ไม่พบโครงการสำหรับ projectId: ${newTransactionData.projectId}` });
  }
  const newTransaction = { ...newTransactionData, id: uuidv4() };
  transactions.push(newTransaction);
  console.log("POST /api/transactions - Added:", newTransaction);
  res.status(201).json(newTransaction);
});

app.put("/api/transactions/:id", (req, res) => {
  const { id } = req.params;
  const updatedTransactionData = req.body;
  const transactionIndex = transactions.findIndex((t) => t.id === id);

  if (transactionIndex === -1) {
    return res.status(404).json({ success: false, message: "ไม่พบธุรกรรม" });
  }

  // Update transaction data (excluding id)
  transactions[transactionIndex] = { ...transactions[transactionIndex], ...updatedTransactionData, id: id };
  console.log("PUT /api/transactions/:id - Updated:", transactions[transactionIndex]);
  res.json(transactions[transactionIndex]);
});

app.delete("/api/transactions/:id", (req, res) => {
  const { id } = req.params;
  const initialLength = transactions.length;
  transactions = transactions.filter((t) => t.id !== id);

  if (transactions.length === initialLength) {
    return res.status(404).json({ success: false, message: "ไม่พบธุรกรรม" });
  }
  console.log(`DELETE /api/transactions/:id - Deleted transaction ${id}`);
  res.status(204).send(); // No content
});

// --- Static files and SPA routing --- //
app.use(express.static(path.join(__dirname, "build")));
app.get("/*", (req, res) => {
  res.sendFile(path.join(__dirname, "build", "index.html"));
});

// Start the server
app.listen(PORT, () => {
  console.log(`Server is running on port ${PORT}`);
});

