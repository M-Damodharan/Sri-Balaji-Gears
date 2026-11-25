export default function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  res.status(200).json({
    status: 'healthy',
    service: 'Sri Balaji Gears Production Web Service (Vercel)',
    timestamp: new Date().toISOString(),
    uptime: '24/7 active (100% Free Forever)'
  });
}
