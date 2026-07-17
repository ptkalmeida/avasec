const fs = require('fs');

function fix() {
  let c1 = fs.readFileSync('src/components/InstructorDashboard.tsx', 'utf8');
  c1 = c1.replace(/docs\.map\(\(doc\) => \{/g, 'docs.map((doc, docIdx) => {');
  c1 = c1.replace(/typeof idx !== "undefined" \? idx : 0/g, 'idx');
  c1 = c1.replace(/courses\.filter\(c => c\.lessons\.length > 0\)\.map\(\(c\) => \{/g, 'courses.filter(c => c.lessons.length > 0).map((c, idx) => {');
  fs.writeFileSync('src/components/InstructorDashboard.tsx', c1, 'utf8');

  let c2 = fs.readFileSync('src/components/StudentDashboard.tsx', 'utf8');
  c2 = c2.replace(/docs\.map\(\(doc\) => \{/g, 'docs.map((doc, docIdx) => {');
  c2 = c2.replace(/filtered\.map\(\(faq\) => \{/g, 'filtered.map((faq, idx) => {');
  c2 = c2.replace(/typeof idx !== "undefined" \? idx : 0/g, 'idx');
  fs.writeFileSync('src/components/StudentDashboard.tsx', c2, 'utf8');
}

fix();
