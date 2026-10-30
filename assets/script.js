// 1. Mobile Menu & Active Navigation Link
const mb = document.querySelector('#menuBtn') || document.querySelector('.menu');
const links = document.querySelector('#navLinks') || document.querySelector('.links');

if (mb && links) {
  mb.addEventListener('click', () => {
    const isOpen = links.classList.toggle('open');
    mb.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });
}

const currentFile = location.pathname.split('/').pop() || 'index.html';
document.querySelectorAll('.links a').forEach(a => {
  const href = (a.getAttribute('href') || '').split('#')[0];
  if (href === currentFile) a.classList.add('active');
});

// 2. Scroll Reveal Observer
const revealObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('show');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// 3. URL Parameter Pre-fill for Contact Enquiry Form
const urlParams = new URLSearchParams(window.location.search);
const preselectedSample = urlParams.get('sample');
const sampleSelectEl = document.getElementById('sampleSelect');
const msgInputEl = document.getElementById('msgInput');

if (preselectedSample && sampleSelectEl) {
  for (const opt of sampleSelectEl.options) {
    if (opt.value.includes(preselectedSample) || preselectedSample.includes(opt.value)) {
      sampleSelectEl.value = opt.value;
      break;
    }
  }
  if (msgInputEl && !msgInputEl.value) {
    msgInputEl.value = `We would like to enquire about ${preselectedSample}. Please share manufacturing lead time and quotation details.`;
  }
}

// 4. Enquiry Mailto Handler
const enquiryForm = document.querySelector('#enquiry');
if (enquiryForm) {
  enquiryForm.addEventListener('submit', e => {
    e.preventDefault();
    const d = new FormData(enquiryForm);
    const sampleVal = d.get('sample') ? `\nProduct / Sample: ${d.get('sample')}` : '';
    const body = encodeURIComponent(
      `Name: ${d.get('name')}\nCompany: ${d.get('company') || 'N/A'}\nEmail: ${d.get('email')}\nPhone: ${d.get('phone') || 'N/A'}${sampleVal}\n\nRequirement:\n${d.get('message')}`
    );
    location.href = `mailto:erp@sribalajigears.com?subject=${encodeURIComponent('Website Enquiry - ' + d.get('name'))}&body=${body}`;
    const statusEl = document.querySelector('#status');
    if (statusEl) {
      statusEl.textContent = 'Your email application will open with your enquiry details. Please review and send.';
      statusEl.style.color = '#fbbf24';
    }
  });
}

// ============================================================================
// 5. INTERACTIVE 3D CUSTOMER SAMPLE VIEWER (THREE.JS + ALL-DEVICE RESIZE)
// ============================================================================
(function initSBG3DViewer() {
  const container = document.getElementById('sbg-3d-canvas');
  if (!container || typeof THREE === 'undefined') return;

  const scene = new THREE.Scene();
  const initW = container.clientWidth || 800;
  const initH = container.clientHeight || 480;

  const camera = new THREE.PerspectiveCamera(40, initW / initH, 0.1, 100);
  camera.position.set(3.3, 2.1, 3.8);

  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setSize(initW, initH);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  container.appendChild(renderer.domElement);

  const controls = new THREE.OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.05;
  controls.autoRotate = true;
  controls.autoRotateSpeed = 1.6;
  controls.maxPolarAngle = Math.PI / 2 + 0.12;
  controls.minDistance = 1.5;
  controls.maxDistance = 10;

  // Studio Lighting
  scene.add(new THREE.AmbientLight(0xffffff, 0.72));

  const keyLight = new THREE.DirectionalLight(0xffffff, 1.15);
  keyLight.position.set(5, 8, 6);
  keyLight.castShadow = true;
  scene.add(keyLight);

  const fillLight = new THREE.DirectionalLight(0x38bdf8, 0.48);
  fillLight.position.set(-6, 3, -4);
  scene.add(fillLight);

  const rimLight = new THREE.DirectionalLight(0xf59e0b, 0.3);
  rimLight.position.set(0, -4, -5);
  scene.add(rimLight);

  // Studio Turntable Platform (No Dimensions)
  const platform = new THREE.Mesh(
    new THREE.CircleGeometry(4.5, 64),
    new THREE.MeshStandardMaterial({ color: 0x07101e, roughness: 0.82, metalness: 0.15 })
  );
  platform.rotation.x = -Math.PI / 2;
  platform.position.y = -1.22;
  platform.receiveShadow = true;
  scene.add(platform);

  const ring = new THREE.Mesh(
    new THREE.RingGeometry(4.42, 4.48, 64),
    new THREE.MeshBasicMaterial({ color: 0xf59e0b, side: THREE.DoubleSide, transparent: true, opacity: 0.38 })
  );
  ring.rotation.x = -Math.PI / 2;
  ring.position.y = -1.21;
  scene.add(ring);

  const productMat = new THREE.MeshStandardMaterial({
    color: 0xcbd5e1,
    metalness: 0.78,
    roughness: 0.22
  });

  const accentMat = new THREE.MeshStandardMaterial({
    color: 0x475569,
    metalness: 0.8,
    roughness: 0.3
  });

  function cylinderBetween(p1, p2, r, mat = productMat) {
    const dir = new THREE.Vector3().subVectors(p2, p1);
    const mesh = new THREE.Mesh(new THREE.CylinderGeometry(r, r, dir.length(), 16), mat);
    mesh.position.copy(p1).add(p2).multiplyScalar(0.5);
    mesh.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir.clone().normalize());
    mesh.castShadow = true;
    return mesh;
  }

  function boxBeamBetween(p1, p2, thickness, mat = productMat) {
    const dir = new THREE.Vector3().subVectors(p2, p1);
    const mesh = new THREE.Mesh(new THREE.BoxGeometry(thickness, dir.length(), thickness), mat);
    mesh.position.copy(p1).add(p2).multiplyScalar(0.5);
    mesh.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir.clone().normalize());
    mesh.castShadow = true;
    return mesh;
  }

  // Build Sample 01: Tubular Grill & Carrier Assembly (Image 1 - No Sizes Shown)
  function createTubularRackSample() {
    const group = new THREE.Group();
    const mainR = 0.035, rodR = 0.018;
    const W = 2.2, D = 1.15, H = 1.3, hW = W / 2;

    const base = new THREE.Group();
    base.add(cylinderBetween(new THREE.Vector3(-hW, 0, 0), new THREE.Vector3(hW, 0, 0), mainR));
    base.add(cylinderBetween(new THREE.Vector3(-hW, 0, D), new THREE.Vector3(hW, 0, D), mainR));
    base.add(cylinderBetween(new THREE.Vector3(-hW, 0, 0), new THREE.Vector3(-hW, 0, D), mainR));
    base.add(cylinderBetween(new THREE.Vector3(hW, 0, 0), new THREE.Vector3(hW, 0, D), mainR));

    base.add(cylinderBetween(new THREE.Vector3(-hW, 0, D * 0.36), new THREE.Vector3(hW, 0, D * 0.36), mainR * 0.82));
    base.add(cylinderBetween(new THREE.Vector3(-hW, 0, D * 0.70), new THREE.Vector3(hW, 0, D * 0.70), mainR * 0.82));

    const numRods = 9;
    for (let i = 0; i < numRods; i++) {
      const x = -hW + 0.18 + (i * (W - 0.36) / (numRods - 1));
      base.add(cylinderBetween(new THREE.Vector3(x, 0, 0), new THREE.Vector3(x, 0, D), rodR));
      if (i % 2 === 0) {
        const loop = new THREE.Mesh(new THREE.TorusGeometry(0.03, 0.01, 10, 20), productMat);
        loop.rotation.x = Math.PI / 2;
        loop.position.set(x, 0, D + 0.03);
        base.add(loop);
      }
    }
    group.add(base);

    const upright = new THREE.Group();
    upright.add(cylinderBetween(new THREE.Vector3(-hW, 0, 0), new THREE.Vector3(-hW, H, 0), mainR));
    upright.add(cylinderBetween(new THREE.Vector3(hW, 0, 0), new THREE.Vector3(hW, H, 0), mainR));
    upright.add(cylinderBetween(new THREE.Vector3(-hW, H, 0), new THREE.Vector3(hW, H, 0), mainR));
    upright.add(cylinderBetween(new THREE.Vector3(-hW, H * 0.25, 0), new THREE.Vector3(hW, H * 0.25, 0), mainR * 0.85));

    for (let i = 0; i < numRods; i++) {
      const x = -hW + 0.18 + (i * (W - 0.36) / (numRods - 1));
      upright.add(cylinderBetween(new THREE.Vector3(x, 0, 0), new THREE.Vector3(x, H, 0), rodR));
    }
    upright.rotation.x = -Math.PI * (10 / 180);
    group.add(upright);

    [-hW, hW].forEach(sideX => {
      const curve = new THREE.QuadraticBezierCurve3(
        new THREE.Vector3(sideX, 0, D * 0.62),
        new THREE.Vector3(sideX, H * 0.18, D * 0.18),
        new THREE.Vector3(sideX, H * 0.65, -0.09)
      );
      const brace = new THREE.Mesh(new THREE.TubeGeometry(curve, 20, mainR * 0.75, 12, false), productMat);
      group.add(brace);

      const hinge = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.05, 0.06, 16), accentMat);
      hinge.rotation.z = Math.PI / 2;
      hinge.position.set(sideX, -0.03, 0.02);
      group.add(hinge);
    });

    group.position.set(0, -0.45, -0.45);
    return group;
  }

  // Build Sample 02: Structural SKD Packing Cage Frame (Image 2 - No Sizes Shown)
  function createStructuralCageSample() {
    const group = new THREE.Group();
    const W = 1.35, D = 0.88, H = 2.42;
    const hW = W / 2, hD = D / 2, midH = 1.15, lowH = 0.25;
    const bT = 0.045, sT = 0.032;

    const posts = [
      [new THREE.Vector3(-hW, 0, hD), new THREE.Vector3(-hW, H, hD)],
      [new THREE.Vector3(hW, 0, hD), new THREE.Vector3(hW, H, hD)],
      [new THREE.Vector3(-hW, 0, -hD), new THREE.Vector3(-hW, H, -hD)],
      [new THREE.Vector3(hW, 0, -hD), new THREE.Vector3(hW, H, -hD)]
    ];
    posts.forEach(([b, t]) => group.add(boxBeamBetween(b, t, bT)));

    [0, midH, H].forEach(y => {
      group.add(boxBeamBetween(new THREE.Vector3(-hW, y, hD), new THREE.Vector3(hW, y, hD), bT));
      group.add(boxBeamBetween(new THREE.Vector3(-hW, y, -hD), new THREE.Vector3(hW, y, -hD), bT));
      group.add(boxBeamBetween(new THREE.Vector3(-hW, y, -hD), new THREE.Vector3(-hW, y, hD), bT));
      group.add(boxBeamBetween(new THREE.Vector3(hW, y, -hD), new THREE.Vector3(hW, y, hD), bT));
    });

    group.add(boxBeamBetween(new THREE.Vector3(-hW, lowH, hD), new THREE.Vector3(hW, lowH, hD), sT));
    group.add(boxBeamBetween(new THREE.Vector3(-hW, lowH, -hD), new THREE.Vector3(hW, lowH, -hD), sT));

    [-0.35, -0.1, 0.15, 0.4].forEach(x => {
      group.add(boxBeamBetween(new THREE.Vector3(x, 0, -hD), new THREE.Vector3(x, 0, hD), sT));
    });

    [[-0.35, 0.15], [0.15, -0.15], [0.4, 0.18]].forEach(([x, z]) => {
      group.add(boxBeamBetween(new THREE.Vector3(x, 0, z), new THREE.Vector3(x, 0.28, z), sT, accentMat));
    });

    [-hW, hW].forEach(sideX => {
      group.add(boxBeamBetween(new THREE.Vector3(sideX, lowH, -hD), new THREE.Vector3(sideX, midH, 0), sT));
      group.add(boxBeamBetween(new THREE.Vector3(sideX, lowH, hD), new THREE.Vector3(sideX, midH, 0), sT));
      group.add(boxBeamBetween(new THREE.Vector3(sideX, 0, 0), new THREE.Vector3(sideX, midH, 0), sT));
    });

    group.position.set(0, -1.18, 0);
    return group;
  }

  const rackModel = createTubularRackSample();
  const cageModel = createStructuralCageSample();
  scene.add(rackModel);
  scene.add(cageModel);
  cageModel.visible = false;

  // Global UI Handlers for 3D Viewer
  window.switchSBGModel = function(mode, btn) {
    document.querySelectorAll('.vtab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const pill = document.getElementById('samplePill');
    const heading = document.getElementById('sampleHeading');
    const summary = document.getElementById('sampleSummary');
    const specs = document.getElementById('sampleSpecs');
    const enquireBtn = document.getElementById('sampleEnquireBtn');

    if (mode === 'rack') {
      rackModel.visible = true;
      cageModel.visible = false;
      rackModel.position.set(0, -0.45, -0.45);
      camera.position.set(3.2, 2.0, 3.6);
      if (pill) pill.textContent = '3D SAMPLE 01 • CUSTOMER VIEW';
      if (heading) heading.textContent = 'Tubular Grill & Carrier Assembly';
      if (summary) summary.textContent = 'Contoured multi-rod tubular frame with angled upright backrest grill, reinforced side gusset curves, and welded mounting loops. Shown as a clean visual sample without engineering dimensions.';
      if (specs) specs.innerHTML = '<li>Formed tubular perimeter &amp; 9-rod mesh grid</li><li>Smooth 360&deg; fillet welds at all cross intersections</li><li>Fixture-controlled repeatability for OEM supply</li>';
      if (enquireBtn) enquireBtn.href = 'contact.html?sample=Sample%2001%20-%20Tubular%20Carrier%20Frame';
    } else if (mode === 'cage') {
      rackModel.visible = false;
      cageModel.visible = true;
      cageModel.position.set(0, -1.18, 0);
      camera.position.set(3.8, 2.3, 4.2);
      if (pill) pill.textContent = '3D SAMPLE 02 • CUSTOMER VIEW';
      if (heading) heading.textContent = 'Structural SKD Cage Frame';
      if (summary) summary.textContent = 'Multi-tier square hollow section welded cage structure featuring a reinforced pallet base, internal mounting brackets, and diagonal side bracing.';
      if (specs) specs.innerHTML = '<li>High-strength hollow section square tube frame</li><li>Diagonal side bracing &amp; corner gussets</li><li>Red Oxide primer &amp; custom OEM finish options</li>';
      if (enquireBtn) enquireBtn.href = 'contact.html?sample=Sample%2002%20-%20Structural%20Cage%20Frame';
    } else {
      rackModel.visible = true;
      cageModel.visible = true;
      rackModel.position.set(-1.55, -0.55, -0.45);
      cageModel.position.set(1.55, -1.18, 0);
      camera.position.set(0, 2.4, 5.5);
      if (pill) pill.textContent = 'DUAL 3D COMPARISON • CUSTOMER VIEW';
      if (heading) heading.textContent = 'Tubular & Structural Samples';
      if (summary) summary.textContent = 'Side-by-side interactive 3D customer view comparing our tubular rod carrier assembly and heavy-duty square tube frame capabilities.';
      if (specs) specs.innerHTML = '<li>Complete tubular &amp; square hollow section capability</li><li>CO₂ / MIG certified welding workmanship</li><li>Custom fabricated to customer drawings</li>';
      if (enquireBtn) enquireBtn.href = 'contact.html?sample=General%20Welding%20/%20Fabrication%20Enquiry';
    }
    controls.target.set(0, 0, 0);
    controls.update();
  };

  window.toggleSBGRotate = function() {
    controls.autoRotate = !controls.autoRotate;
    const btn = document.getElementById('btnAutoRotate');
    if (btn) {
      btn.innerHTML = `&#8635; Auto-Rotate: ${controls.autoRotate ? 'ON' : 'OFF'}`;
      btn.classList.toggle('active', controls.autoRotate);
    }
  };

  window.resetSBGCamera = function() {
    camera.position.set(3.3, 2.1, 3.8);
    controls.target.set(0, 0, 0);
    controls.update();
  };

  window.toggleSBGFullscreen = function() {
    const stage = document.getElementById('viewerStage');
    if (!stage) return;
    if (!document.fullscreenElement) {
      if (stage.requestFullscreen) stage.requestFullscreen();
    } else {
      if (document.exitFullscreen) document.exitFullscreen();
    }
  };

  window.setSBGFinish = function(hex, metal, rough, name, swatchBtn) {
    document.querySelectorAll('.swatch').forEach(s => s.classList.remove('active'));
    if (swatchBtn) swatchBtn.classList.add('active');
    productMat.color.set(hex);
    productMat.metalness = metal;
    productMat.roughness = rough;
    const label = document.getElementById('finishNameLabel');
    if (label) label.textContent = name;
  };

  // All-Device Responsive Fit via ResizeObserver
  const ro = new ResizeObserver(() => {
    const w = container.clientWidth;
    const h = container.clientHeight;
    if (w > 0 && h > 0) {
      camera.aspect = w / h;
      camera.updateProjectionMatrix();
      renderer.setSize(w, h);
    }
  });
  ro.observe(container);

  function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
  }
  animate();
})();
