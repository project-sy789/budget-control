const fs = require('fs');
const { createCanvas } = require('canvas');

// สร้างไอคอน 192x192
function createIcon192() {
  const canvas = createCanvas(192, 192);
  const ctx = canvas.getContext('2d');
  
  // สีพื้นหลัง
  ctx.fillStyle = '#2196f3'; // สีฟ้า material design
  ctx.fillRect(0, 0, 192, 192);
  
  // สร้างอักษร B ตรงกลาง
  ctx.fillStyle = 'white';
  ctx.font = 'bold 120px Arial';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText('B', 192/2, 192/2);
  
  // บันทึกเป็นไฟล์ PNG
  const buffer = canvas.toBuffer('image/png');
  fs.writeFileSync('../public/logo192.png', buffer);
  console.log('Created logo192.png');
}

// สร้างไอคอน 512x512
function createIcon512() {
  const canvas = createCanvas(512, 512);
  const ctx = canvas.getContext('2d');
  
  // สีพื้นหลัง
  ctx.fillStyle = '#2196f3'; // สีฟ้า material design
  ctx.fillRect(0, 0, 512, 512);
  
  // สร้างอักษร B ตรงกลาง
  ctx.fillStyle = 'white';
  ctx.font = 'bold 320px Arial';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText('B', 512/2, 512/2);
  
  // บันทึกเป็นไฟล์ PNG
  const buffer = canvas.toBuffer('image/png');
  fs.writeFileSync('../public/logo512.png', buffer);
  console.log('Created logo512.png');
}

// สร้างไอคอน favicon.ico
function createFavicon() {
  const canvas = createCanvas(64, 64);
  const ctx = canvas.getContext('2d');
  
  // สีพื้นหลัง
  ctx.fillStyle = '#2196f3'; // สีฟ้า material design
  ctx.fillRect(0, 0, 64, 64);
  
  // สร้างอักษร B ตรงกลาง
  ctx.fillStyle = 'white';
  ctx.font = 'bold 40px Arial';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText('B', 64/2, 64/2);
  
  // บันทึกเป็นไฟล์ PNG ก่อน (เนื่องจากไลบรารี canvas ไม่สร้าง ico โดยตรง)
  const buffer = canvas.toBuffer('image/png');
  fs.writeFileSync('../public/favicon.png', buffer);
  console.log('Created favicon.png (needs conversion to .ico)');
}

try {
  createIcon192();
  createIcon512();
  createFavicon();
} catch (err) {
  console.error('Error creating icons:', err);
} 