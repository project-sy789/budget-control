// Controller for handling project management logic

// Thai: คอนโทรลเลอร์สำหรับจัดการตรรกะเกี่ยวกับโครงการ (Projects)
// - สร้างโครงการใหม่
// - ดึงข้อมูลโครงการทั้งหมด
// - ดึงข้อมูลโครงการตาม ID
// - อัปเดตข้อมูลโครงการ
// - ลบโครงการ

const Project = require("../models/Project");
const Transaction = require("../models/Transaction"); // Needed for budget summary

// Create a new project
// Thai: สร้างโครงการใหม่
exports.createProject = async (req, res, next) => {
  const { name, startDate, endDate, initialBudget } = req.body;

  // Thai: ตรวจสอบข้อมูลที่จำเป็นเบื้องต้น
  if (!name || !startDate || !endDate || initialBudget === undefined) {
    const error = new Error("ข้อมูลโครงการไม่ครบถ้วน (ต้องการ: name, startDate, endDate, initialBudget)");
    error.statusCode = 400; // Bad Request
    return next(error);
  }

  try {
    // Thai: เรียกใช้โมเดล Project เพื่อสร้างโครงการใหม่ในฐานข้อมูล
    const newProject = await Project.create({ name, startDate, endDate, initialBudget });
    // Thai: ส่งข้อมูลโครงการที่สร้างใหม่กลับไปใน response (Status 201 Created)
    res.status(201).json({
      success: true,
      message: "สร้างโครงการสำเร็จ",
      data: newProject,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error("Error creating project:", error);
    next(error);
  }
};

// Get all projects
// Thai: ดึงข้อมูลโครงการทั้งหมด
exports.getAllProjects = async (req, res, next) => {
  try {
    // Thai: เรียกใช้โมเดล Project เพื่อดึงข้อมูลโครงการทั้งหมด
    const projects = await Project.findAll();

    // Optional: Enhance with budget summary for each project
    const projectsWithSummary = await Promise.all(projects.map(async (project) => {
        const summary = await Transaction.getProjectBudgetSummary(project.id);
        const initialBudget = parseFloat(project.initial_budget || 0);
        const totalIncome = parseFloat(summary.total_income || 0);
        const totalExpense = parseFloat(summary.total_expense || 0);
        const balance = initialBudget + totalIncome - totalExpense;
        return {
            ...project,
            initial_budget: initialBudget, // Ensure this is also the parsed number
            total_income: totalIncome,
            total_expense: totalExpense,
            current_balance: balance
        };
    }));

    // Thai: ส่งข้อมูลโครงการทั้งหมด (พร้อมสรุปงบประมาณ) กลับไปใน response
    res.status(200).json({
      success: true,
      count: projectsWithSummary.length,
      data: projectsWithSummary,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error("Error getting all projects:", error);
    next(error);
  }
};

// Get a single project by ID
// Thai: ดึงข้อมูลโครงการตาม ID ที่ระบุ
exports.getProjectById = async (req, res, next) => {
  const { id } = req.params; // Project ID from URL parameter

  try {
    // Thai: เรียกใช้โมเดล Project เพื่อค้นหาโครงการด้วย ID
    const project = await Project.findById(id);

    // Thai: ตรวจสอบว่าพบโครงการหรือไม่
    if (!project) {
      const error = new Error("ไม่พบโครงการที่ระบุ");
      error.statusCode = 404; // Not Found
      return next(error);
    }

    // Optional: Add budget summary
    const summary = await Transaction.getProjectBudgetSummary(project.id);
    const initialBudget = parseFloat(project.initial_budget || 0);
    const totalIncome = parseFloat(summary.total_income || 0);
    const totalExpense = parseFloat(summary.total_expense || 0);
    const balance = initialBudget + totalIncome - totalExpense;
    const projectWithSummary = {
        ...project,
        initial_budget: initialBudget, // Ensure this is also the parsed number
        total_income: totalIncome,
        total_expense: totalExpense,
        current_balance: balance
    };

    // Thai: ส่งข้อมูลโครงการกลับไปใน response
    res.status(200).json({
      success: true,
      data: projectWithSummary,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error getting project ${id}:`, error);
    next(error);
  }
};

// Update a project
// Thai: อัปเดตข้อมูลโครงการ
exports.updateProject = async (req, res, next) => {
  const { id } = req.params; // Project ID from URL parameter
  const { name, startDate, endDate, initialBudget } = req.body;

  // Thai: ตรวจสอบข้อมูลที่จำเป็นเบื้องต้น
  if (!name || !startDate || !endDate || initialBudget === undefined) {
    const error = new Error("ข้อมูลโครงการที่ต้องการอัปเดตไม่ครบถ้วน (ต้องการ: name, startDate, endDate, initialBudget)");
    error.statusCode = 400; // Bad Request
    return next(error);
  }

  try {
    // Thai: เรียกใช้โมเดล Project เพื่ออัปเดตข้อมูลโครงการในฐานข้อมูล
    const updatedProject = await Project.update(id, { name, startDate, endDate, initialBudget });

    // Thai: ส่งข้อมูลโครงการที่อัปเดตแล้วกลับไปใน response
    res.status(200).json({
      success: true,
      message: "อัปเดตโครงการสำเร็จ",
      data: updatedProject,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error updating project ${id}:`, error);
    next(error);
  }
};

// Delete a project
// Thai: ลบโครงการ
exports.deleteProject = async (req, res, next) => {
  const { id } = req.params; // Project ID from URL parameter

  try {
    // Thai: เรียกใช้โมเดล Project เพื่อลบโครงการออกจากฐานข้อมูล
    // Note: Associated transactions are deleted by CASCADE constraint in DB
    await Project.delete(id);

    // Thai: ส่ง response ยืนยันการลบ (Status 204 No Content)
    res.status(204).send();

  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error deleting project ${id}:`, error);
    next(error);
  }
};

