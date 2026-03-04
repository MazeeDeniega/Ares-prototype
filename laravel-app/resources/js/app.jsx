import './bootstrap';
import { Route, Routes } from "react-router-dom";
import Login from './pages/Login';
import Register from './pages/Register';
import DashboardAdmin from './pages/DashboardAdmin';
import DashboardRecruiter from './pages/DashboardRecruiter';

const App = () =>{

  return (
    <div className="main-app">
      <Routes>
        <Route path="/" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/admin" element={<DashboardAdmin />} />
        <Route path="/recruiter" element={<DashboardRecruiter />} />
      </Routes>
    </div>
  )

}

const root = document.getElementById('react-root');