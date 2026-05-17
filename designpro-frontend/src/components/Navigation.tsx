import { useState } from 'react';
import { Menu, X, ArrowRight } from 'lucide-react';

export const Navigation = () => {
  const [isOpen, setIsOpen] = useState(false);

  const navLinks = [
    'Home',
    'About Us',
    'Courses',
    'Instructors',
    'Testimonials',
    'Blog',
  ];

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-white/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16 sm:h-20">
          {/* Logo */}
          <div className="flex items-center gap-2 sm:gap-3 flex-shrink-0">
            <div className="w-9 h-9 sm:w-11 sm:h-11 rounded-full border-2 border-white flex items-center justify-center flex-shrink-0">
              <div className="w-3.5 h-3.5 sm:w-5 sm:h-5 rounded-full bg-white" />
            </div>
            <span className="text-white font-bold text-sm sm:text-base whitespace-nowrap">DesignPro</span>
          </div>

          {/* Desktop Navigation */}
          <div className="hidden lg:flex">
            <div className="flex items-center gap-8 px-8 py-2.5 bg-gray-900/60 rounded-full border border-gray-700/50">
              {navLinks.map((link) => (
                <a
                  key={link}
                  href="#"
                  className="text-white/80 text-sm font-medium hover:text-white transition-colors duration-200"
                >
                  {link}
                </a>
              ))}
              <button className="flex items-center gap-2 text-white/80 hover:text-white transition-colors duration-200 text-sm font-medium group">
                Contact us
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" />
              </button>
            </div>
          </div>

          {/* Mobile menu button */}
          <button
            onClick={() => setIsOpen(!isOpen)}
            className="lg:hidden p-2.5 text-white/80 hover:text-white active:text-white transition-colors duration-200 rounded-lg hover:bg-white/10 active:bg-white/20"
            aria-label="Toggle menu"
            aria-expanded={isOpen}
          >
            {isOpen ? (
              <X className="w-6 h-6" />
            ) : (
              <Menu className="w-6 h-6" />
            )}
          </button>
        </div>

        {/* Mobile Navigation */}
        {isOpen && (
          <div className="lg:hidden pb-6 space-y-2 border-t border-white/10 mt-2">
            {navLinks.map((link) => (
              <a
                key={link}
                href="#"
                className="block text-white/80 text-base font-medium hover:text-white transition-colors duration-200 py-3 px-4 rounded-lg hover:bg-white/10 active:bg-white/20"
              >
                {link}
              </a>
            ))}
            <button className="w-full flex items-center justify-between gap-3 text-white/80 hover:text-white transition-colors duration-200 text-base font-medium py-3 px-4 rounded-lg hover:bg-white/10 active:bg-white/20 mt-2 pt-4 border-t border-white/10">
              <span>Contact us</span>
              <ArrowRight className="w-5 h-5 flex-shrink-0" />
            </button>
          </div>
        )}
      </div>
    </nav>
  );
};

