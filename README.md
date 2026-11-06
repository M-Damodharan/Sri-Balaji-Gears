# Sri Balaji Gears — Enterprise Automotive Manufacturing Website

A corporate website for **Sri Balaji Gears** (Hosur, Tamil Nadu), engineered for automotive Tier-1 manufacturing credibility, high responsiveness across all devices, and interactive UI/UX features.

---

## 🌟 Key Features & UI/UX Implementations

1. **Fully Responsive Across All Devices**:
   - Tailored layouts for **Mobile Phones (320px–480px)**, **Tablets (768px–1024px)**, **Laptops (1024px–1440px)**, and **Ultra-wide Desktops (1440px+)**.
   - Fluid typography with CSS `clamp()`, safe-area insets for mobile notches, and touch-target accessibility (>44px).
   - Horizontal overflow prevention and sticky table horizontal scrolling.

2. **Enterprise Navigation & Mobile Drawer**:
   - Sticky glassmorphic header with blur and scroll-shrink dynamics.
   - Off-canvas mobile drawer with smooth slide-in, backdrop blur, category links, click-to-call, and click-to-email.
   - Dynamic active page indicator on both desktop navigation and mobile drawer.

3. **Interactive Components & Micro-Interactions**:
   - **Interactive Number Counters**: Automatically count up when scrolled into view (1999 Established, 400+ Employees, 70,000 Sq. Ft. Land Area, 50,000 Sq. Ft. Built-up Area).
   - **Interactive Tabbed Product Showcase**: Seamless switching between 3-Seat Bus Assemblies, 2-Seat Passenger Frames, Auto Frame Welding, and Heavy Stamping.
   - **Interactive Modal / Lightbox**: High-resolution zoom inspection for management system reference artwork (IATF 16949, ISO 9001, EMS, OHSMS).
   - **Interactive FAQ Accordion**: Expandable Q&A for automotive procurement, engineering blueprints, and weld code standards.
   - **One-Click Clipboard Copying**: Instant copy buttons for phone (`+91 87782 78761`), email (`erp@sribalajigears.com`), and plant address with animated toast notifications.
   - **Floating Action Button (FAB) & Scroll-to-Top**: Quick "Request RFQ" trigger and smooth scroll-to-top button.

4. **9 Complete Corporate Pages**:
   - **Home (`index.html`)**: Hero with animated counters, core capabilities, featured 3-seat bus assembly spotlight, manual vs. robotic welding comparison, customer showcase, FAQ, and CTA.
   - **About (`about.html`)**: Company history since 1999, historical milestones stepper (1999–2024+), 4 core manufacturing pillars, and enterprise fact sheet.
   - **Manufacturing (`manufacturing.html`)**: 6-step integrated workflow pipeline, 6-axis robotic vs. manual welding comparison, installed machinery fleet table.
   - **Products (`products.html`)**: Interactive tabbed product portfolio, engineering data sheet, AIS 023 compliance, tubular ERW material specs.
   - **Infrastructure (`infrastructure.html`)**: 70,000 sq. ft. campus dashboard, 6 specialized industrial bays (press, bending, robotic, manual, tool room, lab), SIPCOT Hosur highway logistics.
   - **Quality (`quality.html`)**: 3-stage gateway (IQC, IPQC, PDI), weld macro-etch lab, dye penetrant crack testing, and quality scorecard (<50 PPM target).
   - **Certifications (`certifications.html`)**: Interactive gallery with click-to-zoom modal for IATF 16949, ISO 9001, EMS, and OHSMS, plus audit scope matrix.
   - **Customers (`customers.html`)**: In-depth profiles for UNO MINDA (strategic bus seating partner) and TVS Motor Company (automotive chassis frame partner), with supplier rating metrics.
   - **Contact (`contact.html`)**: Interactive RFQ portal form with requirement selector, volume estimation, formatted mailto generation, plant directory, copy buttons, and transit directions from Bengaluru.

---

## 🏭 Company Profile Parameters

- **Legal Entity**: Sri Balaji Gears
- **Established**: 1999 (25+ years continuous manufacturing)
- **Workforce**: 400 Employees (updated at company request)
- **Total Land Area**: 70,000 Sq. Ft.
- **Built-Up Area**: Approx. 50,000 Sq. Ft.
- **Location**: SIPCOT Industrial Complex Phase I, Hosur, Tamil Nadu 635126, India
- **Phone**: +91 87782 78761
- **Email**: erp@sribalajigears.com
- **Core OEM Partners**:
  - **UNO MINDA**: Dedicated bus seat frame welding (~90% specialized work focus)
  - **TVS Motor Company**: Auto body frame welding parts (~50% automotive focus)

---

## 🚀 How to Run & Preview

Open `index.html` in any modern web browser or serve via Python local server:

```bash
# Start a local web server:
python -m http.server 8080

# Then open in browser:
http://localhost:8080
```
