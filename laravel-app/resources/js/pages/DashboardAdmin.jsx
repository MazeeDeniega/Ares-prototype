import { useState, useEffect } from "react";
import NavBar from "../components/NavBar";

export default function DashboardAdmin() {
  document.title = "Admin Dashboard";
  const [user, setUser] = useState(null);
  const [session, setSession] = useState({success: null, error: null});

  return (

    <div className="main-cont">
      <NavBar title="Admin" />


    </div>
  );

}