const fs = require('fs');
const path = require('path');
const { createCanvas } = require('canvas');
const png2ico = require('png2ico');

// Configuration
const PUBLIC_DIR = path.join(__dirname, '../public');
const ICON_SIZES = [16, 32, 48, 64]; // Standard favicon sizes

// Create a simple icon with text
function generateFaviconPng(size) {
  const canvas = createCanvas(size, size);
  const ctx = canvas.getContext('2d');
  
  // Background
  ctx.fillStyle = '#2196f3'; // Material UI blue
  ctx.fillRect(0, 0, size, size);
  
  // Small symbol for tiny icons
  if (size <= 32) {
    // Just a simple B for small sizes
    const fontSize = Math.floor(size * 0.7);
    ctx.fillStyle = '#ffffff';
    ctx.font = `bold ${fontSize}px Arial, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('฿', size/2, size/2);
  } else {
    // Inner circle for larger sizes
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
  }
  
  return canvas.toBuffer('image/png');
}

// Generate favicon.ico
async function generateFavicon() {
  console.log('Generating favicon.ico...');
  
  try {
    // Create temp PNGs for each size
    const pngBuffers = [];
    const tempFiles = [];
    
    for (const size of ICON_SIZES) {
      const buffer = generateFaviconPng(size);
      const tempFile = path.join(PUBLIC_DIR, `temp_favicon_${size}.png`);
      fs.writeFileSync(tempFile, buffer);
      tempFiles.push(tempFile);
      pngBuffers.push(buffer);
    }
    
    // Convert PNGs to ICO
    const icoBuffer = await png2ico(pngBuffers);
    fs.writeFileSync(path.join(PUBLIC_DIR, 'favicon.ico'), icoBuffer);
    
    // Clean up temp files
    tempFiles.forEach(file => {
      fs.unlinkSync(file);
    });
    
    console.log('favicon.ico generated successfully!');
  } catch (error) {
    console.error('Error generating favicon:', error);
    process.exit(1);
  }
}

// Execute the favicon generation
generateFavicon(); 