// กำหนดสีของแต่ละกลุ่มงานที่ใช้ทั่วทั้งแอปพลิเคชัน
export interface CustomChipColorProps {
  color: string;
  backgroundColor: string;
  borderColor: string;
}

// ค่าสีของแต่ละกลุ่มงาน
export const workGroupCustomColors: Record<string, CustomChipColorProps> = {
  academic: { color: '#ffffff', backgroundColor: '#1976d2', borderColor: '#1976d2' }, // สีน้ำเงิน
  budget: { color: '#ffffff', backgroundColor: '#9c27b0', borderColor: '#9c27b0' }, // สีม่วง
  hr: { color: '#ffffff', backgroundColor: '#ff9800', borderColor: '#ff9800' }, // สีส้ม
  general: { color: '#ffffff', backgroundColor: '#2e7d32', borderColor: '#2e7d32' }, // สีเขียวเข้ม
  other: { color: '#ffffff', backgroundColor: '#424242', borderColor: '#424242' } // สีเทาเข้ม
};

// Map สำหรับแปลงรหัสกลุ่มงานเป็นชื่อภาษาไทย
export const workGroupTranslations: Record<string, string> = {
  academic: 'กลุ่มงานบริหารวิชาการ',
  budget: 'กลุ่มงานงบประมาณ',
  hr: 'กลุ่มงานบริหารงานบุคคล',
  general: 'กลุ่มงานบริหารทั่วไป',
  other: 'อื่น ๆ'
};

// ฟังก์ชันสำหรับแปลงรหัสกลุ่มงานเป็นชื่อภาษาไทย
export const getWorkGroupLabel = (workGroup: string): string => {
  return workGroupTranslations[workGroup] || workGroup;
};

// ฟังก์ชันสำหรับรับค่าสีของกลุ่มงาน
export const getWorkGroupColors = (workGroup: string): CustomChipColorProps => {
  return workGroupCustomColors[workGroup] || workGroupCustomColors.other;
};

// ส่งออกข้อมูลทั้งหมดเพื่อให้สามารถนำไปใช้ในไฟล์อื่นได้
export default {
  workGroupCustomColors,
  workGroupTranslations,
  getWorkGroupLabel,
  getWorkGroupColors
}; 