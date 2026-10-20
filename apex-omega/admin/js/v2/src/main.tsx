import React from 'react'
import { createRoot } from 'react-dom/client'
import Dashboard from './components/Dashboard'
const el = document.getElementById('apex-v2-root')
if (el) createRoot(el).render(<Dashboard api={(window as any).ApexOmega?.rest_url || '/wp-json'} />)
