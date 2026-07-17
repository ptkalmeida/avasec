const fs = require('fs');
const path = './src/components/StudentDashboard.tsx';
let content = fs.readFileSync(path, 'utf8');

const target1 = `                <div className="space-y-6 text-left">
                  {/* Scenario 1: Active Enrolled Course Card */}
                  {enrollmentRecord.enrolledCourseId ? (`;
const replace1 = `                <div className="space-y-6 text-left">
                  {/* Certificados Resumo Widget */}
                  <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-in fade-in duration-300">
                    <div className="flex items-center gap-4">
                      <div className="p-3 bg-teal-50 text-teal-600 rounded-xl">
                        <Award className="h-6 w-6" />
                      </div>
                      <div>
                        <h3 className="font-black text-slate-800 text-sm uppercase tracking-wider">Meus Certificados</h3>
                        <div className="flex flex-wrap items-center gap-3 mt-1 text-xs text-slate-500">
                          <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-emerald-500"></span>{certificates.filter(c => c.studentName === activeUser.name).length} disponíveis</span>
                          <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-blue-500"></span>{enrollmentRecord.enrolledCourseId ? 1 : 0} em andamento</span>
                        </div>
                      </div>
                    </div>
                    <button onClick={() => setActiveDashboardTab('certificates')} className="shrink-0 px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer border border-slate-200">
                      Ver Certificados
                    </button>
                  </div>

                  {/* Scenario 1: Active Enrolled Course Card */}
                  {enrollmentRecord.enrolledCourseId ? (`;

content = content.replace(target1, replace1);

const target2 = `      ) : activeDashboardTab === 'certificates' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="md:col-span-2 space-y-6">
              {/* Certificate listing block */}
              <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs text-left">
                <div className="flex items-center gap-2 mb-4">
                  <Award className="h-5 w-5 text-teal-600" />
                  <h3 className="font-black text-slate-800 text-sm uppercase tracking-wider">Certificados Obtidos ({certificates.filter(c => c.studentName === activeUser.name).length})</h3>
                </div>
                
                <div className="space-y-3">
                  {certificates.filter((cert) => cert.studentName === activeUser.name).length === 0 ? (
                    <div className="py-12 border-2 border-dashed border-slate-100 rounded-xl flex flex-col items-center justify-center text-slate-400 grayscale opacity-40">
                      <Award className="h-10 w-10 mb-2" />
                      <p className="text-xs font-bold">Nenhum certificado emitido ainda.</p>
                    </div>
                  ) : (
                    certificates.filter((cert) => cert.studentName === activeUser.name).map((cert) => (
                      <div 
                        key={cert.id} 
                        onClick={() => setSelectedCertificate(cert)}
                        className="group p-4 bg-amber-50/20 border border-amber-200/60 rounded-xl flex items-center justify-between cursor-pointer hover:bg-amber-50 transition-colors"
                      >
                        <div className="flex items-center gap-3">
                          <div className="p-2.5 bg-amber-100 text-amber-700 rounded-full border border-amber-200/50">
                            <Award className="h-5 w-5" />
                          </div>
                          <div>
                            <span className="block text-sm font-black text-slate-900 leading-tight group-hover:text-[#540D6E] transition-colors">{cert.courseTitle}</span>
                            <span className="block text-[10px] font-mono text-slate-500 uppercase mt-0.5">Código: {cert.verificationHash}</span>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          <button 
                            className="p-1.5 bg-slate-100 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                            title="Compartilhar no LinkedIn"
                            onClick={(e) => { e.stopPropagation(); showAlert('Compartilhando no LinkedIn...'); }}
                          >
                            <Linkedin className="h-4 w-4" />
                          </button>
                          <button 
                            className="p-1.5 bg-slate-100 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                            title="Baixar PDF"
                            onClick={(e) => { e.stopPropagation(); showAlert('Preparando seu certificado em PDF para impressão...'); }}
                          >
                            <Download className="h-4 w-4" />
                          </button>
                          <ChevronRight className="h-4 w-4 text-amber-400 group-hover:translate-x-0.5 transition-transform" />
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>

            <div className="space-y-6">
              <div className="rounded-2xl bg-white border border-slate-100 p-6 shadow-xs text-left">
                <h4 className="font-black text-slate-800 text-xs uppercase tracking-wider mb-5 flex items-center gap-2">
                  <Settings className="h-4 w-4 text-teal-600" />
                  Dúvidas do Certificado?
                </h4>
                <ol className="space-y-4">
                  <li className="text-[11px] leading-relaxed text-slate-500 flex gap-3">
                    <span className="font-black text-teal-600">1.</span>
                    Cada aula de fixação marcada como visualizada computa progresso de conteúdo teórico.
                  </li>
                  <li className="text-[11px] leading-relaxed text-slate-500 flex gap-3">
                    <span className="font-black text-teal-600">2.</span>
                    Aulas ao vivo computam progresso quando você confirma presença ao entrar na videoconferência integrada.
                  </li>
                  <li className="text-[11px] leading-relaxed text-slate-500 flex gap-3">
                    <span className="font-black text-teal-600">3.</span>
                    A média de freqüência calcula a soma ponderada de todas as aulas e presenciais listados naquele curso. Somados se deve ter no mínimo <strong>70%</strong>.
                  </li>
                  <li className="text-[11px] leading-relaxed text-slate-500 flex gap-3">
                    <span className="font-black text-teal-600">4.</span>
                    Mude seu nome de aluno no topo da página para que o certificado conste seu nome civil oficial!
                  </li>
                </ol>
              </div>
            </div>
          </div>
        </div>
      ) : activeDashboardTab === 'documents' ? (`;

const replace2 = `      ) : activeDashboardTab === 'certificates' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-5xl mx-auto">
          {/* Header */}
          <div className="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-left mb-6">
            <h2 className="text-xl font-black text-slate-900 flex items-center gap-2 mb-2">
              <Award className="h-6 w-6 text-teal-600" />
              Meus Certificados
            </h2>
            <p className="text-xs text-slate-600 bg-white p-3 rounded-lg border border-slate-100 inline-block">
              <span className="font-bold text-teal-700 mr-1">Aviso:</span> O certificado será liberado automaticamente após o cumprimento dos critérios de conclusão do curso.
            </p>
          </div>

          {/* Resumo */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div className="bg-white border border-emerald-100 rounded-2xl p-5 shadow-xs text-left">
              <span className="text-3xl font-black text-emerald-600 block mb-1">{certificates.filter(c => c.studentName === activeUser.name).length}</span>
              <span className="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Certificados Disponíveis</span>
            </div>
            <div className="bg-white border border-blue-100 rounded-2xl p-5 shadow-xs text-left">
              <span className="text-3xl font-black text-blue-600 block mb-1">{enrollmentRecord.enrolledCourseId ? 1 : 0}</span>
              <span className="text-[11px] font-bold text-blue-800 uppercase tracking-wider">Cursos em Andamento</span>
            </div>
            <div className="bg-white border border-amber-100 rounded-2xl p-5 shadow-xs text-left">
              <span className="text-3xl font-black text-amber-600 block mb-1">{
                courses.filter(c => c.id === enrollmentRecord.enrolledCourseId && calculateAttendancePercent(c.id) < (c.minAttendance ?? 70)).length
              }</span>
              <span className="text-[11px] font-bold text-amber-800 uppercase tracking-wider">Certificados Pendentes</span>
            </div>
          </div>

          {/* Tabs Navigation */}
          <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 mb-6 pb-2">
            {[
              { id: 'available', label: 'Disponíveis' },
              { id: 'in_progress', label: 'Em andamento' },
              { id: 'validation', label: 'Validação' },
            ].map((tab) => (
              <button
                key={tab.id}
                onClick={() => {
                  setActiveCertificatesTab(tab.id);
                  setValidationResult(null);
                  setValidationCode('');
                }}
                className={\`px-5 py-2.5 rounded-t-lg text-xs font-bold uppercase tracking-wider transition-all \${
                  activeCertificatesTab === tab.id
                    ? 'bg-slate-800 text-white border-b-2 border-slate-800'
                    : 'bg-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'
                }\`}
              >
                {tab.label}
              </button>
            ))}
          </div>

          {/* Tab Content */}
          <div className="text-left space-y-4">
            {activeCertificatesTab === 'available' && (
              <div className="space-y-4 animate-in fade-in duration-300">
                {certificates.filter(c => c.studentName === activeUser.name).length === 0 ? (
                  <div className="py-12 border-2 border-dashed border-slate-200 bg-slate-50 rounded-2xl flex flex-col items-center justify-center text-slate-500">
                    <Award className="h-10 w-10 mb-3 text-slate-300" />
                    <p className="text-sm font-bold text-slate-700">Você ainda não possui certificados disponíveis.</p>
                    <p className="text-xs mt-1">Conclua um curso para liberar seu primeiro certificado.</p>
                  </div>
                ) : (
                  certificates.filter(c => c.studentName === activeUser.name).map(cert => {
                    const course = courses.find(c => c.id === cert.courseId);
                    const workload = course?.workloadHours ?? 40;
                    return (
                      <div key={cert.id} className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-5">
                        <div className="space-y-2">
                          <div className="flex items-center gap-2">
                            <span className="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded text-[9px] font-black uppercase tracking-wider flex items-center gap-1">
                              <CheckCircle className="h-3 w-3" /> Certificado disponível
                            </span>
                          </div>
                          <h4 className="font-black text-slate-900 text-lg leading-tight">{cert.courseTitle}</h4>
                          <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600 font-medium">
                            <span className="flex items-center gap-1.5"><Calendar className="h-3.5 w-3.5 text-slate-400" /> Concluído em: {cert.issueDate}</span>
                            <span className="flex items-center gap-1.5"><Clock className="h-3.5 w-3.5 text-slate-400" /> Carga Horária: {workload}h</span>
                            <span className="flex items-center gap-1.5"><CheckCircle className="h-3.5 w-3.5 text-slate-400" /> Concluído: 100%</span>
                          </div>
                          <div className="pt-1 flex items-center gap-2 text-[10px] font-mono text-slate-500 bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-100 w-fit">
                            <span>Código: <strong>{cert.verificationHash}</strong></span>
                            <button 
                              onClick={() => {
                                navigator.clipboard.writeText(cert.verificationHash);
                                showAlert('Código copiado para a área de transferência!');
                              }}
                              className="text-teal-600 hover:text-teal-700 font-bold ml-2 uppercase tracking-wider transition-colors cursor-pointer"
                            >
                              Copiar código
                            </button>
                          </div>
                        </div>
                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                          <button
                            onClick={() => setSelectedCertificate(cert)}
                            className="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider px-5 py-2.5 rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 cursor-pointer"
                          >
                            <Download className="h-4 w-4" />
                            Baixar certificado
                          </button>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            )}

            {activeCertificatesTab === 'in_progress' && (
              <div className="space-y-4 animate-in fade-in duration-300">
                {!enrollmentRecord.enrolledCourseId ? (
                  <div className="py-12 border-2 border-dashed border-slate-200 bg-slate-50 rounded-2xl flex flex-col items-center justify-center text-slate-500">
                    <BookOpen className="h-10 w-10 mb-3 text-slate-300" />
                    <p className="text-sm font-bold text-slate-700">Você não possui cursos em andamento no momento.</p>
                  </div>
                ) : (
                  (() => {
                    const activeCourse = courses.find(c => c.id === enrollmentRecord.enrolledCourseId);
                    if (!activeCourse) return null;
                    const attendance = calculateAttendancePercent(activeCourse.id);
                    const minAttendance = activeCourse.minAttendance ?? 70;
                    
                    if (activeCourse.category.includes('Sem Certificado')) {
                      return (
                         <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
                          <h4 className="font-black text-slate-900 text-lg leading-tight mb-2">{activeCourse.title}</h4>
                          <span className="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-[11px] font-bold inline-block">Este curso não possui emissão de certificado.</span>
                        </div>
                      );
                    }

                    return (
                      <div className="bg-white border border-amber-200 rounded-2xl p-5 shadow-xs flex flex-col md:flex-row justify-between gap-6">
                        <div className="space-y-4 w-full max-w-2xl">
                          <div>
                            <h4 className="font-black text-slate-900 text-lg leading-tight mb-1">{activeCourse.title}</h4>
                            <p className="text-xs text-slate-500 font-medium">Você concluiu {attendance}% do curso. Para liberar o certificado, é necessário atingir {minAttendance}%.</p>
                          </div>
                          
                          <div className="space-y-1.5">
                            <div className="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-500">
                              <span>Progresso Atual</span>
                              <span className="text-amber-600 font-black">{attendance}% / {minAttendance}%</span>
                            </div>
                            <div className="w-full bg-slate-100 rounded-full h-2">
                              <div className="bg-amber-500 h-2 rounded-full transition-all duration-500" style={{ width: \`\${Math.min(100, attendance)}%\` }}></div>
                            </div>
                          </div>
                          
                          <div className="bg-amber-50 border border-amber-100 rounded-xl p-3 text-xs text-amber-800">
                            <strong>O que falta?</strong> Continue assistindo as aulas teóricas e conclua os módulos pendentes para atingir o mínimo necessário.
                          </div>
                        </div>
                        <div className="flex items-center shrink-0">
                          <button
                            onClick={() => {
                              setSelectedCourse(activeCourse);
                              setActiveDashboardTab('general');
                              window.scrollTo({ top: 0, behavior: 'smooth' });
                            }}
                            className="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs uppercase tracking-wider px-5 py-2.5 rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 w-full md:w-auto cursor-pointer"
                          >
                            <PlayCircle className="h-4 w-4" />
                            Continuar curso
                          </button>
                        </div>
                      </div>
                    );
                  })()
                )}
              </div>
            )}

            {activeCertificatesTab === 'validation' && (
              <div className="space-y-6 animate-in fade-in duration-300 max-w-2xl">
                <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs">
                  <h3 className="font-black text-slate-800 text-sm uppercase tracking-wider mb-2">Validar um Certificado</h3>
                  <p className="text-xs text-slate-500 mb-6">
                    Insira o código de validação (hash alfanumérico) que consta no certificado para verificar a autenticidade e os dados de emissão.
                  </p>
                  
                  <div className="flex flex-col sm:flex-row gap-3">
                    <input
                      type="text"
                      placeholder="Ex: CERT-8F2X-99P1"
                      value={validationCode}
                      onChange={(e) => setValidationCode(e.target.value)}
                      className="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 font-mono focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                    />
                    <button
                      onClick={() => {
                        if (!validationCode.trim()) return;
                        const found = certificates.find(c => c.verificationHash === validationCode.trim());
                        if (found) {
                          const course = courses.find(c => c.id === found.courseId);
                          setValidationResult({
                            valid: true,
                            message: 'Certificado válido',
                            studentName: found.studentName,
                            courseTitle: found.courseTitle,
                            workloadHours: course?.workloadHours ?? 40,
                            completionDate: found.issueDate
                          });
                        } else {
                          setValidationResult({
                            valid: false,
                            message: 'Certificado não encontrado. Confira se o código foi digitado corretamente.'
                          });
                        }
                      }}
                      className="bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-xl transition-all shadow-xs cursor-pointer"
                    >
                      Validar
                    </button>
                  </div>

                  {validationResult && (
                    <div className={\`mt-6 p-5 rounded-xl border animate-in slide-in-from-bottom-2 duration-300 \${
                      validationResult.valid ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'
                    }\`}>
                      {validationResult.valid ? (
                        <div className="space-y-3">
                          <div className="flex items-center gap-2 text-emerald-700 mb-2">
                            <CheckCircle className="h-5 w-5" />
                            <strong className="text-sm uppercase tracking-wider">Certificado Válido</strong>
                          </div>
                          <div className="space-y-1.5 text-xs text-slate-700">
                            <p><strong className="text-slate-900 w-24 inline-block">Aluno:</strong> {validationResult.studentName}</p>
                            <p><strong className="text-slate-900 w-24 inline-block">Curso:</strong> {validationResult.courseTitle}</p>
                            <p><strong className="text-slate-900 w-24 inline-block">Carga Horária:</strong> {validationResult.workloadHours}h</p>
                            <p><strong className="text-slate-900 w-24 inline-block">Conclusão:</strong> {validationResult.completionDate}</p>
                            <p><strong className="text-slate-900 w-24 inline-block">Código:</strong> <span className="font-mono bg-white px-1.5 py-0.5 rounded border border-slate-100">{validationCode}</span></p>
                          </div>
                        </div>
                      ) : (
                        <div className="flex items-center gap-3 text-amber-800">
                          <Info className="h-5 w-5 shrink-0" />
                          <p className="text-xs font-bold leading-relaxed">{validationResult.message}</p>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      ) : activeDashboardTab === 'documents' ? (`;

content = content.replace(target2, replace2);
fs.writeFileSync(path, content);
