import { useEffect, useState } from 'react'
export default function Dashboard({api}:{api:string}) {
  const [stats,setStats]=useState<any>(null)
  useEffect(()=>{ fetch(api+'/apex/v2/stats').then(r=>r.json()).then(setStats).catch(()=>{}) },[api])
  return (
    <div className="apex-v2-dashboard">
      <h2>APEX Ω 2.0 — داشبورد زنده</h2>
      <pre>{JSON.stringify(stats,null,2) || 'در حال بارگذاری...'}</pre>
      <div id="apex-v2-chart" style={{height:280, background:'#f5f3ff', borderRadius:12, display:'grid', placeItems:'center'}}>
        Chart.js اینجا (timeseries)
      </div>
    </div>
  )
}
