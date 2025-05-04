const fs = require('fs');
const path = require('path');
const { createCanvas } = require('canvas');

// Configuration
const PUBLIC_DIR = path.join(__dirname, '../public');
const ICONS = [
  { filename: 'logo192.png', size: 192 },
  { filename: 'logo512.png', size: 512 }
];

// Create a simple icon with text
function generateIcon(size) {
  const canvas = createCanvas(size, size);
  const ctx = canvas.getContext('2d');
  
  // Background
  ctx.fillStyle = '#2196f3'; // Material UI blue
  ctx.fillRect(0, 0, size, size);
  
  // Inner circle
  ctx.fillStyle = '#ffffff';
  ctx.beginPath();
  ctx.arc(size/2, size/2, size*0.35, 0, Math.PI * 2);
  ctx.fill();
  
  // Text
  const fontSize = Math.floor(size * 0.4);
  ctx.fillStyle = '#2196f3';
  ctx.font = `bold ${fontSize}px Arial, sans-serif`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText('฿', size/2, size/2);
  
  return canvas.toBuffer('image/png');
}

// Generate all icon sizes
function generateIcons() {
  console.log('Generating app icons...');
  
  try {
    ICONS.forEach(icon => {
      const buffer = generateIcon(icon.size);
      const filePath = path.join(PUBLIC_DIR, icon.filename);
      
      fs.writeFileSync(filePath, buffer);
      console.log(`Created ${icon.filename} (${icon.size}x${icon.size})`);
    });
    
    console.log('All icons generated successfully!');
  } catch (error) {
    console.error('Error generating icons:', error);
    process.exit(1);
  }
}

// Execute the icon generation
generateIcons(); 