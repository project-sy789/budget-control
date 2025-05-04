export const BudgetCategory = {
  SUBSIDY: 'เงินอุดหนุนรายหัว',
  DEVELOPMENT: 'เงินพัฒนาผู้เรียน',
  INCOME: 'เงินรายได้สถานศึกษา',
  EQUIPMENT: 'เงินค่าอุปกรณ์การเรียน',
  UNIFORM: 'เงินค่าเครื่องแบบ',
  BOOKS: 'เงินค่าหนังสือ',
  LUNCH: 'เงินอาหารกลางวัน'
} as const;

export const WorkGroup = {
  academic: 'กลุ่มงานบริหารวิชาการ',
  budget: 'กลุ่มงานงบประมาณ',
  hr: 'กลุ่มงานบริหารงานบุคคล',
  general: 'กลุ่มงานบริหารทั่วไป',
  other: 'อื่น ๆ'
} as const;

export type BudgetCategoryType = keyof typeof BudgetCategory;

export interface BudgetCategoryItem {
  category: BudgetCategoryType;
  amount: number;
  description: string;
}

export interface Project {
  id: string;
  name: string;
  budget: number;
  workGroup: 'academic' | 'budget' | 'hr' | 'general' | 'other';
  responsiblePerson: string;
  description?: string;
  startDate: string;
  endDate: string;
  budgetCategories: BudgetCategoryItem[];
  status: 'active' | 'completed';
}

export interface Transaction {
  id: string;
  projectId: string;
  date: string;
  description: string;
  amount: number;
  budgetCategory: BudgetCategoryType;
  note?: string;
  isTransfer?: boolean;
  isTransferIn?: boolean;
  transferToProjectId?: string;
  transferToCategory?: BudgetCategoryType;
  transferFromProjectId?: string;
  transferFromCategory?: BudgetCategoryType;
}

// ระบบจัดการผู้ใช้
export type UserRole = 'admin' | 'user' | 'pending';

export interface User {
  uid: string;
  email: string;
  displayName: string;
  photoURL?: string;
  role: UserRole;
  createdAt: string;
  lastLogin?: string;
  approved: boolean;
  department?: string;
  position?: string;
}

export interface LoginRequest {
  email: string;
  token: string;
}

export interface AuthState {
  user: User | null;
  loading: boolean;
  error: string | null;
  initialized: boolean;
  message?: string | null;
} 