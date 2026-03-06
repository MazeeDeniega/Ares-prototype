import './bootstrap';
import { BrowserRouter, Route, Routes, Link } from "react-router-dom";
import { createRoot } from 'react-dom/client';
import Login from './pages/Login';
import Register from './pages/Register';
import DashboardAdmin from './pages/DashboardAdmin';
import DashboardRecruiter from './pages/DashboardRecruiter';


createRoot(document.getElementById('app')).render(
  <BrowserRouter>
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/admin" element={<DashboardAdmin />} />
      <Route path="/recruiter" element={<DashboardRecruiter />} />
      <Route path="*" element={<p>404 - Page not found</p>} />
    </Routes>
  </BrowserRouter>
);
// const App = () =>{

//   return (
//     <div className="main-app">
//       <Routes>
//         <Route path="/" element={<Login />} />
//         <Route path="/admin" element={<DashboardAdmin />} />
//       </Routes>
//     </div>
//   )

// }

// const root = document.getElementById('react-root');